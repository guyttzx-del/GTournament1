/* GTournament1 progressive enhancement layer. */
const ajaxAdminActions = new Set([
  'save_season', 'duplicate_season', 'archive_season', 'delete_season',
  'change_staff_role', 'disable_staff', 'review_registration', 'resolve_match_dispute'
]);

function closeMenu() {
  const toggle = document.querySelector('.menu-toggle');
  const navigation = document.querySelector('.main-nav');
  if (!toggle || !navigation) return;
  navigation.classList.remove('is-open');
  toggle.setAttribute('aria-expanded', 'false');
  toggle.setAttribute('aria-label', 'เปิดเมนู');
}

function bindMenu() {
  const toggle = document.querySelector('.menu-toggle');
  const navigation = document.querySelector('.main-nav');
  if (!toggle || !navigation || toggle.dataset.bound === 'true') return;
  toggle.dataset.bound = 'true';
  toggle.addEventListener('click', () => {
    const isOpen = navigation.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(isOpen));
    toggle.setAttribute('aria-label', isOpen ? 'ปิดเมนู' : 'เปิดเมนู');
  });
  navigation.addEventListener('click', (event) => {
    if (event.target instanceof HTMLAnchorElement) closeMenu();
  });
}

function bindApplicantForm() {
  const panel = document.querySelector('.applicant-panel');
  const choices = [...document.querySelectorAll('.applicant-choice')];
  if (!panel || choices.length < 2 || panel.dataset.bound === 'true') return;
  panel.dataset.bound = 'true';
  const selectApplicant = (type) => {
    const isNew = type === 'new';
    panel.classList.toggle('is-new', isNew);
    const typeField = panel.querySelector('input[name="applicant_type"]');
    if (typeField) typeField.value = isNew ? 'new' : 'returning';
    choices.forEach((choice, index) => {
      const selected = isNew ? index === 1 : index === 0;
      choice.classList.toggle('is-selected', selected);
      choice.setAttribute('aria-pressed', String(selected));
    });
  };
  choices.forEach((choice, index) => {
    choice.setAttribute('aria-pressed', String(index === 0));
    choice.addEventListener('click', () => selectApplicant(index === 1 ? 'new' : 'existing'));
  });
  selectApplicant('existing');
}

function bindProfilePreview() {
  const file = document.querySelector('input[name="profile_image"]');
  const upload = file?.closest('.profile-upload');
  if (!file || !upload || file.dataset.bound === 'true') return;
  file.dataset.bound = 'true';
  const standard = () => '<div class="profile-preview-avatar" aria-hidden="true">GT</div><span>รูปโปรไฟล์มาตรฐาน</span>';
  const preview = document.createElement('div');
  preview.className = 'profile-preview';
  preview.innerHTML = standard();
  upload.insertBefore(preview, file);
  file.addEventListener('change', () => {
    const selected = file.files?.[0];
    if (!selected || !['image/jpeg', 'image/png'].includes(selected.type) || selected.size > 5 * 1024 * 1024) {
      file.value = '';
      preview.innerHTML = standard();
      return;
    }
    const reader = new FileReader();
    reader.addEventListener('load', () => {
      const image = document.createElement('img');
      image.src = String(reader.result || '');
      image.alt = 'ตัวอย่างรูปโปรไฟล์ที่เลือก';
      const filename = document.createElement('span');
      filename.textContent = selected.name;
      preview.replaceChildren(image, filename);
    });
    reader.readAsDataURL(selected);
  });
}

function bindPlayerSearch() {
  const card = document.querySelector('.applicant-search-card');
  const button = card?.querySelector('button');
  const input = card?.querySelector('input[name="existing_player_query"]');
  if (!card || !button || !input || button.dataset.bound === 'true') return;
  button.dataset.bound = 'true';
  button.addEventListener('click', async () => {
    const query = input.value.trim();
    if (query.length < 2) return;
    button.disabled = true;
    try {
      const response = await fetch(`?view=player-search&q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
      const players = await response.json();
      let results = card.querySelector('.public-player-results');
      if (!results) { results = document.createElement('div'); results.className = 'public-player-results'; card.append(results); }
      results.replaceChildren();
      if (!players.length) { results.textContent = 'ไม่พบผู้เล่นที่ตรงกัน'; return; }
      players.forEach((player) => {
        const result = document.createElement('button');
        result.type = 'button'; result.className = 'public-player-result';
        const strong = document.createElement('strong'); strong.textContent = player.competition_name || '';
        const span = document.createElement('span'); span.textContent = `${player.nickname || ''} · ${player.club || 'ไม่ระบุคลับ'}`;
        result.append(strong, span);
        result.addEventListener('click', () => {
          const form = card.closest('form');
          if (!form) return;
          let hidden = form.querySelector('input[name="existing_player_id"]');
          if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'existing_player_id'; form.append(hidden); }
          hidden.value = player.id || '';
          input.value = player.competition_name || '';
          results.querySelectorAll('button').forEach((item) => item.classList.remove('is-selected'));
          result.classList.add('is-selected');
        });
        results.append(result);
      });
    } catch { /* Keep registration usable when search is unavailable. */ }
    finally { button.disabled = false; }
  });
}

function ensureDeleteSeasonButtons() {
  document.querySelectorAll('.admin-season-item').forEach((seasonCard) => {
    const editLink = seasonCard.querySelector('a[href*="edit_season="]');
    const csrfSource = seasonCard.querySelector('input[name="_csrf"]');
    if (!editLink || !csrfSource || seasonCard.querySelector('[data-delete-season]')) return;
    const seasonId = new URL(editLink.href, window.location.href).searchParams.get('edit_season');
    if (!seasonId) return;
    const form = document.createElement('form');
    form.method = 'post'; form.dataset.deleteSeason = 'true';
    form.innerHTML = '<input type="hidden" name="action" value="delete_season"><input type="hidden" name="season_id"><input type="hidden" name="_csrf">';
    form.querySelector('input[name="season_id"]').value = seasonId;
    form.querySelector('input[name="_csrf"]').value = csrfSource.value;
    const button = document.createElement('button');
    button.type = 'submit'; button.className = 'btn btn-outline'; button.textContent = 'ลบ Season'; button.dataset.deleteSeason = 'true';
    form.append(button); seasonCard.querySelector('.button-row')?.append(form);
  });
}

function showAjaxStatus(message, tone = 'error') {
  let status = document.querySelector('[data-ajax-status]');
  if (!status) {
    status = document.createElement('div');
    status.dataset.ajaxStatus = 'true'; status.setAttribute('role', 'status'); document.body.append(status);
  }
  status.textContent = message; status.dataset.tone = tone; status.hidden = false;
  window.clearTimeout(Number(status.dataset.timer || 0));
  status.dataset.timer = String(window.setTimeout(() => { status.hidden = true; }, 4500));
}

function setFormBusy(form, busy) {
  form.classList.toggle('is-loading', busy);
  form.querySelectorAll('button, input[type="submit"]').forEach((control) => {
    if (busy) {
      control.dataset.originalLabel = control.textContent || control.value || '';
      if (control.tagName === 'BUTTON') control.textContent = 'กำลังดำเนินการ…';
    } else if (control.dataset.originalLabel) {
      if (control.tagName === 'BUTTON') control.textContent = control.dataset.originalLabel;
      delete control.dataset.originalLabel;
    }
    control.disabled = busy;
  });
}

function refreshPageFragment(parsed, url) {
  const currentMain = document.querySelector('main');
  const nextMain = parsed.querySelector('main');
  if (!currentMain || !nextMain) throw new Error('ไม่พบพื้นที่แสดงผลหลังดำเนินการ');
  currentMain.innerHTML = nextMain.innerHTML;
  const currentTopbar = document.querySelector('.topbar');
  const nextTopbar = parsed.querySelector('.topbar');
  if (currentTopbar && nextTopbar) currentTopbar.innerHTML = nextTopbar.innerHTML;
  if (url) window.history.replaceState({}, '', url);
  document.title = parsed.title || document.title;
  bindMenu(); bindApplicantForm(); bindProfilePreview(); bindPlayerSearch(); ensureDeleteSeasonButtons();
  currentMain.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function requestPage(url, options = {}) {
  const response = await fetch(url, { credentials: 'same-origin', ...options });
  const html = await response.text();
  const parsed = new DOMParser().parseFromString(html, 'text/html');
  if (parsed.querySelector('main')) {
    refreshPageFragment(parsed, response.url);
    return response.ok;
  }
  if (!response.ok) throw new Error(response.status === 403 ? 'ไม่มีสิทธิ์เข้าถึงหน้านี้' : `ระบบตอบกลับผิดพลาด (${response.status})`);
  throw new Error('ไม่พบพื้นที่แสดงผลหลังดำเนินการ');
}

function isInternalLink(link) {
  if (!link || link.target === '_blank' || link.hasAttribute('download') || link.getAttribute('href')?.startsWith('#')) return false;
  try {
    const url = new URL(link.href, window.location.href);
    return url.origin === window.location.origin && !url.pathname.includes('/assets/');
  } catch { return false; }
}

document.addEventListener('click', async (event) => {
  const link = event.target instanceof Element ? event.target.closest('a') : null;
  if (!isInternalLink(link) || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
  event.preventDefault();
  if (document.body.dataset.pageBusy === 'true') return;
  document.body.dataset.pageBusy = 'true'; document.body.classList.add('is-loading');
  try {
    const ok = await requestPage(link.href);
    if (!ok) showAjaxStatus('ระบบหลักยังไม่พร้อม กรุณาตรวจสอบข้อความบนหน้าเว็บ', 'error');
  }
  catch (error) {
    if (error instanceof Error && error.message.includes('(404)')) {
      window.location.assign(link.href);
      return;
    }
    showAjaxStatus(error instanceof Error ? error.message : 'ระบบขัดข้อง กรุณาลองใหม่');
  }
  finally { document.body.dataset.pageBusy = 'false'; document.body.classList.remove('is-loading'); }
});

document.addEventListener('submit', async (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) return;
  if (form.dataset.deleteSeason === 'true' && !window.confirm('ยืนยันลบ Season นี้? ระบบจะลบได้เฉพาะ Season ที่ไม่มีใบสมัครหรือ Match เท่านั้น')) { event.preventDefault(); return; }
  const method = (form.method || 'get').toLowerCase();
  const action = form.querySelector('input[name="action"]')?.value;
  const shouldAjax = method === 'post' || (method === 'get' && form.closest('main'));
  if (!shouldAjax || form.dataset.ajaxBusy === 'true') return;
  event.preventDefault(); form.dataset.ajaxBusy = 'true'; setFormBusy(form, true);
  try {
    const body = method === 'post' ? new FormData(form) : undefined;
    const formAction = form.action || window.location.href;
    const target = new URL(formAction, window.location.href);
    if (method === 'get') target.search = new URLSearchParams(new FormData(form)).toString();
    const url = method === 'get' ? target.href : formAction;
    const ok = await requestPage(url, { method: method.toUpperCase(), body, headers: method === 'post' ? { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } : { Accept: 'text/html' } });
    showAjaxStatus(ok ? (action ? 'ดำเนินการสำเร็จ' : 'โหลดข้อมูลสำเร็จ') : 'ระบบหลักยังไม่พร้อม กรุณาตรวจสอบข้อความบนหน้าเว็บ', ok ? 'success' : 'error');
  } catch (error) { showAjaxStatus(error instanceof Error ? error.message : 'ระบบขัดข้อง กรุณาลองใหม่'); }
  finally { form.dataset.ajaxBusy = 'false'; if (document.body.contains(form)) setFormBusy(form, false); }
});

document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenu(); });
window.addEventListener('resize', () => { if (window.innerWidth > 800) closeMenu(); });
bindMenu(); bindApplicantForm(); bindProfilePreview(); bindPlayerSearch(); ensureDeleteSeasonButtons();

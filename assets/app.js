const menuToggle = document.querySelector('.menu-toggle');
const mainNavigation = document.querySelector('.main-nav');

function closeMenu() {
  if (!menuToggle || !mainNavigation) return;
  mainNavigation.classList.remove('is-open');
  menuToggle.setAttribute('aria-expanded', 'false');
  menuToggle.setAttribute('aria-label', 'เปิดเมนู');
}

menuToggle?.addEventListener('click', () => {
  if (!mainNavigation) return;

  const isOpen = mainNavigation.classList.toggle('is-open');
  menuToggle.setAttribute('aria-expanded', String(isOpen));
  menuToggle.setAttribute('aria-label', isOpen ? 'ปิดเมนู' : 'เปิดเมนู');
});

mainNavigation?.addEventListener('click', (event) => {
  if (event.target instanceof HTMLAnchorElement) closeMenu();
});

window.addEventListener('resize', () => {
  if (window.innerWidth > 800) closeMenu();
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') closeMenu();
});

const applicantPanel = document.querySelector('.applicant-panel');
const applicantChoices = [...document.querySelectorAll('.applicant-choice')];

function selectApplicant(type) {
  if (!applicantPanel || applicantChoices.length < 2) return;
  const isNew = type === 'new';
  applicantPanel.classList.toggle('is-new', isNew);
  const typeField = applicantPanel.querySelector('input[name="applicant_type"]');
  if (typeField) typeField.value = isNew ? 'new' : 'returning';
  applicantChoices.forEach((choice, index) => {
    const selected = isNew ? index === 1 : index === 0;
    choice.classList.toggle('is-selected', selected);
    choice.setAttribute('aria-pressed', String(selected));
  });
}

if (applicantPanel && applicantChoices.length >= 2) {
  applicantChoices.forEach((choice, index) => {
    choice.setAttribute('aria-pressed', String(index === 0));
    choice.addEventListener('click', () => selectApplicant(index === 1 ? 'new' : 'existing'));
  });
  selectApplicant('existing');
}

const profileFile = document.querySelector('input[name="profile_image"]');
const profileUpload = profileFile?.closest('.profile-upload');

if (profileFile && profileUpload) {
  const preview = document.createElement('div');
  preview.className = 'profile-preview';
  preview.innerHTML = '<div class="profile-preview-avatar" aria-hidden="true">GT</div><span>รูปโปรไฟล์มาตรฐาน</span>';
  profileUpload.insertBefore(preview, profileFile);

  profileFile.addEventListener('change', () => {
    const file = profileFile.files?.[0];
    const allowed = ['image/jpeg', 'image/png'];
    if (!file || !allowed.includes(file.type) || file.size > 5 * 1024 * 1024) {
      profileFile.value = '';
      preview.innerHTML = '<div class="profile-preview-avatar" aria-hidden="true">GT</div><span>รูปโปรไฟล์มาตรฐาน</span>';
      return;
    }
    const reader = new FileReader();
    reader.addEventListener('load', () => {
      const image = document.createElement('img');
      image.src = String(reader.result || '');
      image.alt = 'ตัวอย่างรูปโปรไฟล์ที่เลือก';
      const filename = document.createElement('span');
      filename.textContent = file.name;
      preview.replaceChildren(image, filename);
    });
    reader.readAsDataURL(file);
  });
}

const playerSearchCard = document.querySelector('.applicant-search-card');
const playerSearchButton = playerSearchCard?.querySelector('button');
const playerSearchInput = playerSearchCard?.querySelector('input[name="existing_player_query"]');
if (playerSearchCard && playerSearchButton && playerSearchInput) {
  playerSearchButton.addEventListener('click', async () => {
    const query = playerSearchInput.value.trim();
    if (query.length < 2) return;
    playerSearchButton.disabled = true;
    try {
      const response = await fetch(`?view=player-search&q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
      const players = await response.json();
      let results = playerSearchCard.querySelector('.public-player-results');
      if (!results) { results = document.createElement('div'); results.className = 'public-player-results'; playerSearchCard.append(results); }
      results.replaceChildren();
      if (!players.length) { results.textContent = 'ไม่พบผู้เล่นที่ตรงกัน'; return; }
      players.forEach((player) => {
        const button = document.createElement('button');
        button.type = 'button'; button.className = 'public-player-result';
        const strong = document.createElement('strong'); strong.textContent = player.competition_name || '';
        const span = document.createElement('span'); span.textContent = `${player.nickname || ''} · ${player.club || 'ไม่ระบุคลับ'}`;
        button.append(strong, span);
        button.addEventListener('click', () => {
          const form = playerSearchCard.closest('form');
          if (!form) return;
          let hidden = form.querySelector('input[name="existing_player_id"]');
          if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'existing_player_id'; form.append(hidden); }
          hidden.value = player.id || '';
          playerSearchInput.value = player.competition_name || '';
          results.querySelectorAll('button').forEach((item) => item.classList.remove('is-selected'));
          button.classList.add('is-selected');
        });
        results.append(button);
      });
    } catch { /* Keep the registration form usable when search is unavailable. */ }
    finally { playerSearchButton.disabled = false; }
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

ensureDeleteSeasonButtons();

const ajaxAdminActions = new Set([
  'save_season', 'duplicate_season', 'archive_season', 'delete_season',
  'change_staff_role', 'disable_staff', 'review_registration', 'resolve_match_dispute'
]);

function showAjaxStatus(message, tone = 'error') {
  let status = document.querySelector('[data-ajax-status]');
  if (!status) {
    status = document.createElement('div');
    status.dataset.ajaxStatus = 'true';
    status.setAttribute('role', 'status');
    document.body.append(status);
  }
  status.textContent = message;
  status.dataset.tone = tone;
  status.hidden = false;
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

async function submitAdminFormWithoutReload(form) {
  const response = await fetch(form.action || window.location.href, {
    method: 'POST',
    body: new FormData(form),
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }
  });
  const html = await response.text();
  if (!response.ok) throw new Error(response.status === 403 ? 'ไม่มีสิทธิ์ดำเนินการรายการนี้' : 'ระบบไม่สามารถดำเนินการได้ในขณะนี้');
  const parsed = new DOMParser().parseFromString(html, 'text/html');
  const nextMain = parsed.querySelector('main');
  const currentMain = document.querySelector('main');
  if (!nextMain || !currentMain) throw new Error('ไม่พบพื้นที่แสดงผลหลังดำเนินการ');
  currentMain.innerHTML = nextMain.innerHTML;
  if (response.url) window.history.replaceState({}, '', response.url);
  document.title = parsed.title || document.title;
  ensureDeleteSeasonButtons();
  currentMain.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

document.addEventListener('submit', async (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) return;
  if (form.dataset.deleteSeason === 'true' && !window.confirm('ยืนยันลบ Season นี้? ระบบจะลบได้เฉพาะ Season ที่ไม่มีใบสมัครหรือ Match เท่านั้น')) {
    event.preventDefault();
    return;
  }
  const action = form.querySelector('input[name="action"]')?.value;
  if (!ajaxAdminActions.has(action)) return;
  event.preventDefault();
  if (form.dataset.ajaxBusy === 'true') return;
  form.dataset.ajaxBusy = 'true';
  setFormBusy(form, true);
  try {
    await submitAdminFormWithoutReload(form);
    showAjaxStatus('ดำเนินการสำเร็จ', 'success');
  } catch (error) {
    showAjaxStatus(error instanceof Error ? error.message : 'ระบบขัดข้อง กรุณาลองใหม่', 'error');
  } finally {
    form.dataset.ajaxBusy = 'false';
    if (document.body.contains(form)) setFormBusy(form, false);
  }
});

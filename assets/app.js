document.querySelector('.menu-toggle')?.addEventListener('click', () => {
  const nav = document.querySelector('.main-nav');
  const visible = nav?.dataset.open === '1';
  if (!nav) return;
  nav.dataset.open = visible ? '0' : '1';
  nav.style.display = visible ? '' : 'flex';
  nav.style.position = visible ? '' : 'absolute';
  nav.style.top = visible ? '' : '70px';
  nav.style.left = visible ? '' : '0';
  nav.style.right = visible ? '' : '0';
  nav.style.height = visible ? '' : 'auto';
  nav.style.padding = visible ? '' : '15px 28px';
  nav.style.background = visible ? '' : '#121212';
  nav.style.flexDirection = visible ? '' : 'column';
});

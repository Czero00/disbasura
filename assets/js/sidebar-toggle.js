(function () {
  const sidebar = document.querySelector('.sidebar, .res-sidebar');
  if (!sidebar) return;
  const app = document.querySelector('#appRoot, .res-layout, .app') || document.body;
  const main = document.querySelector('.main, .res-main');
  if (!main) return;
  const mobile = () => window.innerWidth <= 768;

  let button = document.getElementById('resHamBtn') || document.getElementById('mobToggle');
  const existingResidentButton = button && button.id === 'resHamBtn';
  if (existingResidentButton) return; // resident dashboard owns its existing control
  if (!button) {
    button = document.createElement('button');
    button.type = 'button';
    button.className = 'sidebar-layout-toggle';
    button.setAttribute('aria-label', 'Hide sidebar');
    button.setAttribute('aria-expanded', 'true');
    button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/><path d="M9 6v12"/></svg>';
  }

  const brand = sidebar.querySelector('.sidebar-brand');
  const topbar = main.querySelector('.topbar');
  let tools = main.querySelector('.sidebar-layout-tools');
  if (!tools && !topbar) {
    tools = document.createElement('div');
    tools.className = 'sidebar-layout-tools';
    main.insertBefore(tools, main.firstChild);
  }
  const moveOutside = () => {
    const host = topbar || tools;
    if (host) host.insertBefore(button, host.firstChild);
  };
  const moveInside = () => { if (brand) brand.appendChild(button); };
  if (mobile()) moveOutside(); else moveInside();

  let overlay = document.querySelector('.sidebar-layout-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'mob-overlay sidebar-layout-overlay';
    document.body.appendChild(overlay);
  }
  const open = () => {
    sidebar.classList.add('open');
    overlay.classList.add('active');
    moveInside();
    button.setAttribute('aria-expanded', 'true');
    button.setAttribute('aria-label', 'Hide sidebar');
  };
  const close = () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    moveOutside();
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-label', 'Show sidebar');
  };
  button.addEventListener('click', function (event) {
    event.preventDefault();
    if (mobile()) {
      sidebar.classList.contains('open') ? close() : open();
      return;
    }
    const hidden = app.classList.toggle('sidebar-collapsed');
    hidden ? moveOutside() : moveInside();
    button.setAttribute('aria-expanded', String(!hidden));
    button.setAttribute('aria-label', hidden ? 'Show sidebar' : 'Hide sidebar');
  });
  overlay.addEventListener('click', close);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
  window.addEventListener('resize', () => {
    if (mobile() && !sidebar.classList.contains('open')) moveOutside();
    else if (!mobile() && !app.classList.contains('sidebar-collapsed')) moveInside();
  });
})();

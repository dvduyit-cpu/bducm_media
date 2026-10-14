(() => {
  const root = document.documentElement;
  const user = document.querySelector('meta[name="menu-user"]')?.content;
  if (!user) return;
  let saved = {}, groups = {};
  try { saved = JSON.parse(localStorage.getItem('bdu-menu-' + user)) || {}; } catch (_) {}
  try { groups = JSON.parse(localStorage.getItem('bdu-menu-groups-' + user)) || {}; } catch (_) {}
  const visible = window.matchMedia('(min-width: 1001px)').matches && saved.visible !== false;
  root.classList.toggle('menu-visible', visible);
  root.classList.toggle('menu-pinned', visible && saved.pinned !== false);
  root.classList.toggle('menu-open', visible && saved.pinned === false);
  ['work', 'organization', 'system'].forEach(group => {
    root.setAttribute('data-menu-' + group, groups[group] === false ? 'closed' : 'open');
  });
})();

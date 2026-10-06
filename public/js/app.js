document.addEventListener('DOMContentLoaded', () => {
  const menuButton = document.querySelector('[data-toggle-menu]');
  const sidebar = document.getElementById('sidebar');
  const pinButton = document.querySelector('[data-pin-menu]');
  const desktop = window.matchMedia('(min-width: 1001px)');
  const menuKey = 'bdu-menu-' + document.body.dataset.menuUser;
  let pinned = true, desktopVisible = true, mobileOpen = false;
  try {
    const saved = JSON.parse(localStorage.getItem(menuKey));
    if (saved) { pinned = saved.pinned !== false; desktopVisible = saved.visible !== false; }
  } catch (_) {}
  const renderMenu = () => {
    if (!sidebar) return;
    const visible = desktop.matches ? desktopVisible : mobileOpen;
    document.body.classList.toggle('menu-visible', visible);
    document.body.classList.toggle('menu-pinned', desktop.matches && visible && pinned);
    document.body.classList.toggle('menu-open', visible && (!desktop.matches || !pinned));
    menuButton?.setAttribute('aria-expanded', String(visible));
    sidebar.inert = !visible;
    sidebar.setAttribute('aria-hidden', String(!visible));
    pinButton?.setAttribute('aria-pressed', String(pinned));
    if (pinButton) {
      const label = pinned ? 'Bỏ ghim menu' : 'Ghim menu';
      pinButton.setAttribute('aria-label', label);
    }
    const pinnedIcon = menuButton?.querySelector('[data-menu-pinned-icon]');
    const drawerIcon = menuButton?.querySelector('[data-menu-drawer-icon]');
    if (pinnedIcon) pinnedIcon.hidden = !(desktop.matches && visible && pinned);
    if (drawerIcon) drawerIcon.hidden = desktop.matches && visible && pinned;
    try { localStorage.setItem(menuKey, JSON.stringify({ pinned, visible: desktopVisible })); } catch (_) {}
  };
  const closeMenu = () => {
    if (desktop.matches) desktopVisible = false; else mobileOpen = false;
    renderMenu();
  };
  menuButton?.addEventListener('click', () => {
    if (desktop.matches) desktopVisible = !desktopVisible; else mobileOpen = !mobileOpen;
    renderMenu();
  });
  pinButton?.addEventListener('click', () => { pinned = !pinned; renderMenu(); });
  document.querySelectorAll('[data-close-menu]').forEach(button => button.addEventListener('click', closeMenu));
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && (!desktop.matches || !pinned)) closeMenu(); });
  document.querySelectorAll('.sidebar .nav-link').forEach(link => link.addEventListener('click', () => { if (!desktop.matches || !pinned) closeMenu(); }));
  desktop.addEventListener('change', () => { mobileOpen = false; renderMenu(); });
  renderMenu();
  const colorInput = document.getElementById('theme-color');
  const colorHex = document.getElementById('theme-color-hex');
  const setColor = color => {
    if (!/^#[0-9a-f]{6}$/i.test(color)) return false;
    color = color.toLowerCase();
    const channels = [1, 3, 5].map(offset => parseInt(color.slice(offset, offset + 2), 16) / 255);
    const linear = channels.map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
    const luminance = linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
    document.documentElement.style.setProperty('--primary', color);
    document.documentElement.style.setProperty('--on-primary', luminance > 0.179 ? '#111827' : '#ffffff');
    if (colorInput) colorInput.value = color;
    if (colorHex) { colorHex.value = color.toUpperCase(); colorHex.setCustomValidity(''); }
    document.querySelectorAll('[data-color]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.color === color)));
    return true;
  };
  colorInput?.addEventListener('input', () => setColor(colorInput.value));
  colorHex?.addEventListener('input', () => {
    colorHex.setCustomValidity(setColor(colorHex.value.trim()) ? '' : 'Nhập mã màu gồm # và 6 ký tự, ví dụ #2563EB.');
  });
  document.querySelectorAll('[data-color]').forEach(button => button.addEventListener('click', () => setColor(button.dataset.color)));
  if (colorInput) setColor(colorInput.value);
  const department = document.getElementById('department-select');
  const contacts = document.getElementById('contact-select');
  const filterContacts = () => { if (!department || !contacts) return; [...contacts.options].forEach(option => { option.hidden = option.dataset.department !== department.value; option.disabled = option.hidden; }); if (contacts.selectedOptions[0]?.disabled) contacts.value = [...contacts.options].find(option => !option.disabled)?.value || ''; };
  department?.addEventListener('change', filterContacts); filterContacts();
  const transition = document.getElementById('transition-select');
  const updateFields = () => {
    document.querySelectorAll('[data-target-field]').forEach(field => {
      const show = field.dataset.targetField === transition?.value;
      field.hidden = !show;
      field.querySelectorAll('input,select,textarea').forEach(input => { input.disabled = !show; input.required = show && input.hasAttribute('data-required'); });
    });
    const note = transition?.form?.querySelector('[name="note"]');
    if (note) note.required = ['completed','cancelled','needs_info'].includes(transition.value);
    window.updateCoordinationFields?.();
  };
  transition?.addEventListener('change', updateFields); updateFields();
  document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
});

window.updateCoordinationFields = () => {
  document.querySelectorAll('[data-coordination-form]').forEach(form => {
    const mode = form.querySelector('[data-coordination-mode]');
    const picker = form.querySelector('[data-support-options]');
    if (!mode || !picker) return;
    const active = !mode.disabled && mode.value === 'support';
    picker.hidden = !active;
    const boxes = [...picker.querySelectorAll('input[type="checkbox"]')];
    boxes.forEach(box => { box.disabled = !active; box.required = false; box.setCustomValidity(''); });
    if (active && boxes[0] && !boxes.some(box => box.checked)) boxes[0].setCustomValidity('Chọn ít nhất một phòng ban hỗ trợ.');
  });
};
document.addEventListener('change', event => {
  if (event.target.matches('[data-coordination-mode], [data-support-options] input')) window.updateCoordinationFields();
});
window.updateCoordinationFields();

// Show progress only for actions that navigate this window.
document.addEventListener('DOMContentLoaded', () => {
  const indicator = document.querySelector('[data-page-loading]');
  if (!indicator) return;
  let storedStyle;
  try { storedStyle = localStorage.getItem('bdu-loading-style'); } catch (_) {}
  const initialStyle = indicator.dataset.loadingStyle || storedStyle;
  indicator.dataset.loadingStyle = ['center', 'top'].includes(initialStyle) ? initialStyle : 'center';
  if (initialStyle) { try { localStorage.setItem('bdu-loading-style', indicator.dataset.loadingStyle); } catch (_) {} }
  const loadingHome = indicator.parentElement;
  let delay, recovery;

  const reset = () => {
    clearTimeout(delay); clearTimeout(recovery); indicator.hidden = true;
    if (indicator.parentElement !== loadingHome) loadingHome.append(indicator);
  };
  const show = label => {
    clearTimeout(delay);
    delay = setTimeout(() => {
      indicator.querySelector('[data-loading-label]').textContent = label;
      indicator.hidden = false;
      const dialog = document.querySelector('dialog[open]');
      if (dialog) dialog.append(indicator);
    }, 150);
    clearTimeout(recovery); recovery = setTimeout(reset, 20000);
  };
  const stylePicker = document.getElementById('loading-style');
  if (stylePicker) indicator.dataset.loadingStyle = stylePicker.value;
  stylePicker?.addEventListener('change', () => { reset(); indicator.dataset.loadingStyle = stylePicker.value; });
  document.querySelector('[data-preview-loading]')?.addEventListener('click', () => {
    reset(); show('Đang tải…'); clearTimeout(recovery); recovery = setTimeout(reset, 1500);
  });
  const downloads = url => url.searchParams.has('export') || /\/(attachments|download)(\/|$)/.test(url.pathname);
  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || !['http:', 'https:'].includes(url.protocol) || downloads(url) || (url.pathname === location.pathname && url.search === location.search)) return;
    queueMicrotask(() => { if (!event.defaultPrevented) show('Đang tải trang…'); });
  });
  document.addEventListener('submit', event => {
    const form = event.target;
    if ((form.target && form.target !== '_self') || downloads(new URL(form.action, location.href)) || form.querySelector('[name="export"]')) return;
    queueMicrotask(() => {
      if (!event.defaultPrevented) show(form.method.toLowerCase() === 'get' ? 'Đang tải dữ liệu…' : 'Đang xử lý…');
    });
  });
  window.addEventListener('pageshow', reset);
  window.addEventListener('pagehide', reset);
});

document.addEventListener('DOMContentLoaded', () => {
  const family = document.getElementById('font-family');
  const size = document.getElementById('font-size');
  if (!family || !size) return;
  const preview = () => {
    document.documentElement.style.setProperty('--app-font', family.selectedOptions[0].dataset.font);
    document.documentElement.style.setProperty('--font-scale', Number(size.value) / 16);
  };
  family.addEventListener('change', preview); size.addEventListener('change', preview);
  document.querySelector('[data-reset-typography]')?.addEventListener('click', () => { family.value = 'system'; size.value = '16'; preview(); });
  preview();
});

document.addEventListener('DOMContentLoaded', () => {
  const eye = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="password-eye-slash" d="m4 4 16 16"/></svg>';
  document.querySelectorAll('input[type="password"]').forEach((input, index) => {
    if (input.closest('.password-field')) return;
    if (!input.id) input.id = 'password-input-' + index;
    const wrapper = document.createElement('span'); wrapper.className = 'password-field';
    input.before(wrapper); wrapper.append(input);
    const button = document.createElement('button'); button.type = 'button'; button.className = 'password-toggle';
    button.innerHTML = eye; button.setAttribute('aria-controls', input.id); button.setAttribute('aria-label', 'Hiện mật khẩu'); button.setAttribute('aria-pressed', 'false');
    wrapper.append(button);
    const hide = () => { input.type = 'password'; button.setAttribute('aria-pressed', 'false'); button.setAttribute('aria-label', 'Hiện mật khẩu'); };
    button.addEventListener('click', event => {
      event.preventDefault();
      const visible = input.type === 'password'; input.type = visible ? 'text' : 'password';
      button.setAttribute('aria-pressed', String(visible)); button.setAttribute('aria-label', visible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
    });
    window.addEventListener('pageshow', hide);
    document.addEventListener('close', event => { if (event.target.contains(input)) hide(); }, true);
  });
});

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form [name="role"]').forEach(role => {
    const reviewer = role.form.querySelector('[name="reviewer_access"]');
    const updateScope = (applyDefaults = false) => {
      const option = role.selectedOptions[0];
      const base = option?.dataset.baseRole || role.value;
      let permissions = [];
      try { permissions = JSON.parse(option?.dataset.permissions || '[]'); } catch (_) {}
      const reviews = reviewer?.checked || false;
      role.form.querySelectorAll('[name="permissions[]"]').forEach(box => {
        const included = reviews && box.value === 'requests';
        box.disabled = included || !permissions.includes(box.value);
        if (included) box.checked = true;
        else if (box.disabled) box.checked = false;
        else if (applyDefaults) box.checked = true;
      });
      const label = role.form.querySelector('[data-global-scope]');
      if (!label) return;
      const allowed = !reviews && ['office', 'director'].includes(base);
      label.hidden = !allowed;
      label.querySelector('input').disabled = !allowed;
      if (!allowed) label.querySelector('input').checked = false;
      const note = role.form.querySelector('[data-reviewer-scope-note]');
      if (note) note.hidden = !(base === 'office' && !reviews && !label.querySelector('input').checked);
    };
    role.addEventListener('change', event => updateScope(event.isTrusted));
    reviewer?.addEventListener('change', () => updateScope());
    role.form.querySelector('[name="view_all_units"]')?.addEventListener('change', () => updateScope());
    updateScope();
  });
  document.querySelectorAll('[data-role-base]').forEach(base => {
    const update = () => {
      const box = base.form.querySelector('[name="permissions[]"][value="approvals"]');
      if (!box) return;
      box.disabled = base.value !== 'office';
      if (box.disabled) box.checked = false;
    };
    base.addEventListener('change', update); update();
  });
});

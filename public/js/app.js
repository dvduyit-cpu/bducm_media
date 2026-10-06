document.addEventListener('DOMContentLoaded', () => {
  const menuButton = document.querySelector('[data-toggle-menu]');
  const closeMenu = () => { document.body.classList.remove('menu-open'); menuButton?.setAttribute('aria-expanded', 'false'); };
  menuButton?.addEventListener('click', () => { const open = document.body.classList.toggle('menu-open'); menuButton.setAttribute('aria-expanded', String(open)); });
  document.querySelector('[data-close-menu]')?.addEventListener('click', closeMenu);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
  document.querySelectorAll('.sidebar .nav-link').forEach(link => link.addEventListener('click', closeMenu));
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
  const updateFields = () => document.querySelectorAll('[data-target-field]').forEach(field => { const show = field.dataset.targetField === transition?.value; field.hidden = !show; field.querySelectorAll('input,select').forEach(input => { input.disabled = !show; input.required = show; }); });
  transition?.addEventListener('change', updateFields); updateFields();
  document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
});

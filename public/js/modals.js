document.addEventListener('DOMContentLoaded', () => {
  const forms = [...document.querySelectorAll('main form[method="post" i]')];
  const oldInput = JSON.parse(document.getElementById('modal-old-input')?.textContent || '{}');
  const serialize = form => JSON.stringify([...new FormData(form)].map(([key, value]) => [key, value instanceof File ? `${value.name}:${value.size}` : value]));
  const restore = form => {
    [...form.elements].forEach(field => {
      if (!field.name || field.name.startsWith('_') || ['password', 'file'].includes(field.type)) return;
      const path = field.name.replace(/\]/g, '').split(/\[/).filter(Boolean);
      const value = path.reduce((object, key) => object?.[key], oldInput);
      if (field.type === 'checkbox') field.checked = Array.isArray(value) ? value.map(String).includes(field.value) : String(value ?? '') === field.value;
      else if (value !== undefined) field.value = value;
    });
    form.querySelectorAll('select').forEach(field => field.dispatchEvent(new Event('change', { bubbles: true })));
  };
  forms.filter(form => !form.hasAttribute('data-modal')).forEach(form => {
    const key = new URL(form.action).pathname;
    const marker = document.createElement('input');
    marker.type = 'hidden'; marker.name = '_inline_form'; marker.value = key; form.append(marker);
    if (oldInput._inline_form === key) restore(form);
  });
  const modalForms = forms.filter(form => form.hasAttribute('data-modal'));
  if (!modalForms.length) return;
  const dialog = document.createElement('dialog');
  dialog.className = 'action-modal';
  dialog.setAttribute('aria-labelledby', 'action-modal-title');
  dialog.innerHTML = '<header class="modal-header"><h2 id="action-modal-title"></h2><button class="icon-button" type="button" data-modal-close aria-label="Đóng cửa sổ">×</button></header><div class="modal-body"></div><section class="modal-discard" hidden><h3>Bỏ thay đổi chưa lưu?</h3><p>Thông tin bạn vừa nhập chưa được lưu.</p><div class="form-actions"><button type="button" class="button secondary" data-keep>Tiếp tục nhập</button><button type="button" class="button primary" data-discard>Bỏ thay đổi</button></div></section>';
  document.body.append(dialog);
  const body = dialog.querySelector('.modal-body');
  const discard = dialog.querySelector('.modal-discard');
  const title = dialog.querySelector('h2');
  let activeForm = null, home = null, opener = null, originalValues = '', initialFields = [], lastFocus = null;
  let busy = false;
  const keep = () => {
    discard.hidden = true; body.hidden = false;
    lastFocus?.focus();
  };
  const close = () => {
    if (busy) return;
    if (activeForm) home.append(activeForm);
    activeForm = null; body.replaceChildren();
    discard.hidden = true; body.hidden = false;
    dialog.close(); document.body.classList.remove('modal-open'); opener?.focus();
  };
  const requestClose = () => {
    if (busy) return;
    if (!discard.hidden) { keep(); return; }
    if (activeForm && serialize(activeForm) !== originalValues) {
      lastFocus = document.activeElement;
      body.hidden = true; discard.hidden = false;
      discard.querySelector('[data-keep]').focus();
    } else close();
  };
  dialog.querySelector('[data-modal-close]').addEventListener('click', requestClose);
  dialog.addEventListener('cancel', event => { event.preventDefault(); requestClose(); });
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) requestClose();
  });
  discard.querySelector('[data-keep]').addEventListener('click', keep);
  discard.querySelector('[data-discard]').addEventListener('click', () => {
    initialFields.forEach(({ field, value, checked }) => {
      field.value = field.type === 'file' ? '' : value;
      if ('checked' in field) field.checked = checked;
    });
    activeForm.querySelectorAll('select').forEach(field => field.dispatchEvent(new Event('change', { bubbles: true })));
    close();
  });
  modalForms.forEach((form, index) => {
    const key = new URL(form.action).pathname + ':' + index;
    const marker = document.createElement('input'); marker.type = 'hidden'; marker.name = '_modal_form'; marker.value = key; form.append(marker);
    const hasErrors = oldInput._modal_form === key && document.querySelector('.alert.error');
    if (hasErrors) restore(form);
    const details = form.closest('details');
    const summary = details?.querySelector(':scope > summary');
    const submit = form.querySelector('button[type="submit"]');
    const label = form.dataset.modalTitle || (details?.classList.contains('user-row') ? 'Chỉnh sửa tài khoản · ' + (summary.querySelector('strong')?.textContent || '') : summary?.innerText.trim() || submit?.innerText.trim() || 'Cập nhật');
    const container = document.createElement('div'); container.className = 'modal-form-home'; form.before(container);
    const launch = document.createElement('button'); launch.type = 'button'; launch.className = 'button primary modal-launch';
    launch.textContent = label; launch.setAttribute('aria-haspopup', 'dialog'); container.append(launch);
    const stored = document.createElement('div'); stored.hidden = true; container.append(stored); stored.append(form);
    const open = trigger => {
      if (dialog.open || trigger.closest('dialog')) return;
      activeForm = form; home = stored; opener = trigger; originalValues = serialize(form);
      initialFields = [...form.elements].map(field => ({ field, value: field.value, checked: field.checked }));
      if (hasErrors) body.append(document.querySelector('.alert.error').cloneNode(true));
      body.append(form); title.textContent = label; document.body.classList.add('modal-open'); dialog.showModal();
      form.querySelector('input:not([type="hidden"]),select,textarea')?.focus();
    };
    const external = [...document.querySelectorAll('[data-modal-trigger]')].find(trigger => trigger.dataset.modalTrigger === form.id);
    if (external) {
      launch.hidden = true;
      external.setAttribute('aria-haspopup', 'dialog');
      external.addEventListener('click', () => open(external));
    }
    launch.addEventListener('click', () => open(launch));
    if (summary) {
      launch.hidden = true; summary.setAttribute('aria-haspopup', 'dialog'); summary.setAttribute('role', 'button');
      summary.addEventListener('click', event => { event.preventDefault(); open(summary); });
      details.removeAttribute('open');
    }
    form.addEventListener('submit', event => {
      if (busy) { event.preventDefault(); return; }
      busy = true; form.setAttribute('aria-busy', 'true');
      form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
    });
    if (hasErrors) open(external || summary || launch);
  });
});
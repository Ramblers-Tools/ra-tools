document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-ra-area-group-field]').forEach((root) => {
    const fieldId = root.getAttribute('data-ra-area-group-field');
    const fieldOptions = Joomla.getOptions('areaGroupField') || {};
    const options = fieldOptions[fieldId]
      || Joomla.getOptions(`areaGroupField.${fieldId}`)
      || {};
    const area = document.getElementById(options.areaId);
    const group = document.getElementById(options.groupId);
    const label = document.getElementById(options.labelId);
    const modalElement = document.getElementById(options.modalId);
    const confirm = modalElement?.querySelector('[data-ra-area-group-confirm]');
    const hidden = document.getElementById(fieldId);
    const launch = root.querySelector('[data-ra-area-group-launch]');

    if (!area || !group || !label || !confirm || !hidden) return;

    const closeModal = () => {
      if (window.bootstrap?.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
      } else {
        modalElement.style.display = 'none';
        modalElement.classList.remove('show');
        modalElement.setAttribute('aria-hidden', 'true');
      }
    };

    modalElement.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        closeModal();
      });
    });

    launch?.addEventListener('click', (event) => {
      event.preventDefault();
      if (window.bootstrap?.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
      } else {
        modalElement.style.display = 'block';
        modalElement.classList.add('show');
        modalElement.setAttribute('aria-hidden', 'false');
      }
    });

    area.addEventListener('change', async () => {
      group.innerHTML = '<option value="">Select Group</option>';
      group.disabled = true;
      confirm.disabled = true;
      if (!area.value) return;

      if (area.value === 'N') {
        group.innerHTML = '<option value="N">All groups</option>';
        group.disabled = false;
        group.value = 'N';
        confirm.disabled = false;
        return;
      }

      try {
        const response = await fetch(`${options.ajaxUrl}&area=${encodeURIComponent(area.value)}`, {
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await response.json();
        let groups = json?.data;
        if (typeof groups === 'string') {
          try { groups = JSON.parse(groups); } catch (error) { groups = []; }
        }
        if (!Array.isArray(groups) && Array.isArray(groups?.data)) groups = groups.data;
        (Array.isArray(groups) ? groups : []).forEach((item) => {
          const option = document.createElement('option');
          option.value = item.code;
          option.textContent = `${item.code} - ${item.name}`;
          group.appendChild(option);
        });
        group.disabled = group.options.length <= 1;
      } catch (error) {
        console.error('Unable to load groups', error);
      }
    });

    group.addEventListener('change', () => {
      confirm.disabled = !group.value;
    });

    confirm.addEventListener('click', () => {
      hidden.value = group.value;
      label.textContent = `${area.options[area.selectedIndex].text} / ${group.options[group.selectedIndex].text}`;
      closeModal();
    });
  });
});

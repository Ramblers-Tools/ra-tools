document.addEventListener('DOMContentLoaded', () => {
  const opts = Joomla.getOptions('areaGroupModal') || {};
  const ajaxUrl = opts.ajaxUrl || 'index.php?option=com_ajax&plugin=plg_ra_ajaxgroups&format=json';

  const areaSelect = document.getElementById('areaSelect');
  const groupSelect = document.getElementById('groupSelect');
  const confirmBtn = document.getElementById('confirmAreaGroupSelection');
  const label = document.getElementById('areaGroupSelectionLabel');

  const areaHidden = document.getElementById('jform_area_code');
  const groupHidden = document.getElementById('jform_group_code');

  if (!areaSelect || !groupSelect || !confirmBtn || !areaHidden || !groupHidden) return;

  areaSelect.addEventListener('change', async () => {
    const areaCode = areaSelect.value;

    groupSelect.innerHTML = '<option value="">-- Select Group --</option>';
    groupSelect.disabled = true;
    confirmBtn.disabled = true;

    if (!areaCode) return;

    try {
      const url = `${ajaxUrl}&area=${encodeURIComponent(areaCode)}`;
      const response = await fetch(url, { credentials: 'same-origin' });
      const json = await response.json();

      const groups = Array.isArray(json?.data) ? json.data : [];

      groups.forEach((g) => {
        const opt = document.createElement('option');
        opt.value = g.code;
        opt.textContent = `${g.code} - ${g.name}`;
        groupSelect.appendChild(opt);
      });

      groupSelect.disabled = groups.length === 0;
    } catch (e) {
      console.error('Failed loading groups', e);
    }
  });

  groupSelect.addEventListener('change', () => {
    confirmBtn.disabled = !groupSelect.value;
  });

  confirmBtn.addEventListener('click', () => {
    const areaCode = areaSelect.value;
    const groupCode = groupSelect.value;
    const areaText = areaSelect.options[areaSelect.selectedIndex]?.text || areaCode;
    const groupText = groupSelect.options[groupSelect.selectedIndex]?.text || groupCode;

    areaHidden.value = areaCode;
    groupHidden.value = groupCode;
    label.textContent = `${areaText} / ${groupText}`;

    const modalEl = document.getElementById('areaGroupModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
  });
});

<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

// Register and use your JS file
$wa->registerAndUseScript(
    'com_example.area-group-modal',
    'media/com_example/js/area-group-modal.js',
    [],
    ['defer' => true]
);

// Pass config to JS
$doc = Factory::getApplication()->getDocument();
$doc->addScriptOptions('areaGroupModal', [
    'ajaxUrl' => Uri::base() . 'index.php?option=com_ajax&plugin=areagroups&format=json',
]);

// $areas should come from your model/query: [{code, name}, ...]
?>
<input type="hidden" name="jform[area_code]" id="jform_area_code" value="">
<input type="hidden" name="jform[group_code]" id="jform_group_code" value="">

<div class="mb-2">
  <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#areaGroupModal">
    Select Area / Group
  </button>
  <span id="areaGroupSelectionLabel" class="ms-2 text-muted">No selection</span>
</div>

<div class="modal fade" id="areaGroupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Area and Group</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <div class="mb-3">
          <label for="areaSelect" class="form-label">Area</label>
          <select id="areaSelect" class="form-select">
            <option value="">-- Select Area --</option>
            <?php foreach ($areas as $area): ?>
              <option value="<?= htmlspecialchars($area->code, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($area->code . ' - ' . $area->name, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label for="groupSelect" class="form-label">Group</label>
          <select id="groupSelect" class="form-select" disabled>
            <option value="">-- Select Group --</option>
          </select>
        </div>

      </div>
      <div class="modal-footer">
        <button id="confirmAreaGroupSelection" type="button" class="btn btn-success" disabled>
          Use Selection
        </button>
      </div>
    </div>
  </div>
</div>

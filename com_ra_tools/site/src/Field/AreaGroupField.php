<?php
/**
 * @package Joomla.Component
 */

namespace Ramblers\Component\Ra_tools\Site\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

final class AreaGroupField extends ListField
{
    protected $type = 'AreaGroup';

    protected function getInput()
    {
        $doc = Factory::getApplication()->getDocument();
        $wa = $doc->getWebAssetManager();

        // Load your JS
        $wa->registerAndUseScript(
            'com_ra_tools.area-group-field',
            'media/com_ra_tools/js/area-group-modal.js',
            [],
            ['defer' => true]
        );

        // Unique IDs
        $idBase = $this->id;
        $modalId = $idBase . '_modal';
        $areaId = $idBase . '_area';
        $groupId = $idBase . '_group';
        $labelId = $idBase . '_label';
        $hiddenId = $idBase;

        // Areas for first dropdown
        $areas = $this->getAreas();

        // Optional: load current selected label
        $currentLabel = $this->getGroupLabel((string) $this->value);

        // Pass per-field config to JS
        $fieldOptions = $doc->getScriptOptions('areaGroupField') ?: [];
        $fieldOptions[$hiddenId] = [
            'fieldId' => $hiddenId,
            'modalId' => $modalId,
            'areaId' => $areaId,
            'groupId' => $groupId,
            'labelId' => $labelId,
            'ajaxUrl' => Uri::base() . 'index.php?option=com_ajax&plugin=ra_selectgroup&group=ajax&format=json',
        ];
        $doc->addScriptOptions('areaGroupField', $fieldOptions);

        $html = [];

        // Hidden value that Joomla saves
        $html[] = '<input type="hidden"'
            . ' name="' . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8') . '"'
            . ' id="' . htmlspecialchars($hiddenId, ENT_QUOTES, 'UTF-8') . '"'
            . ' value="' . htmlspecialchars((string) $this->value, ENT_QUOTES, 'UTF-8') . '">';

        // Visible launcher + current selection
        $html[] = '<div class="d-flex align-items-center gap-2" data-ra-area-group-field="' . htmlspecialchars($hiddenId, ENT_QUOTES, 'UTF-8') . '">';
        $html[] = ' <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#' . $modalId . '" data-ra-area-group-launch>'
            . 'Select Area / Group</button>';
        $html[] = ' <span id="' . $labelId . '" class="text-muted">'
            . htmlspecialchars($currentLabel ?: 'No selection', ENT_QUOTES, 'UTF-8') . '</span>';
        $html[] = '</div>';

        // Modal
        $html[] = '<div class="modal fade" id="' . $modalId . '" tabindex="-1" aria-hidden="true">';
        $html[] = ' <div class="modal-dialog"><div class="modal-content">';
        $html[] = ' <div class="modal-header">';
        $html[] = ' <h5 class="modal-title">Select Area / Group</h5>';
        $html[] = ' <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
        $html[] = ' </div>';
        $html[] = ' <div class="modal-body">';

        $html[] = ' <div class="mb-3">';
        $html[] = ' <label for="' . $areaId . '" class="form-label">Area</label>';
        $html[] = ' <select id="' . $areaId . '" class="form-select" data-ra-area-group-area>';
        $html[] = ' <option value="">Select Area</option>';
        $html[] = ' <option value="N">All groups</option>';
        foreach ($areas as $area) {
            $code = (string) $area['code'];
            $name = (string) $area['name'];
            $html[] = ' <option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                . '</option>';
        }
        $html[] = ' </select>';
        $html[] = ' </div>';

        $html[] = ' <div class="mb-3">';
        $html[] = ' <label for="' . $groupId . '" class="form-label">Group</label>';
        $html[] = ' <select id="' . $groupId . '" class="form-select" data-ra-area-group-group disabled>';
        $html[] = ' <option value="">Select Group</option>';
        $html[] = ' </select>';
        $html[] = ' </div>';

        $html[] = ' </div>';
        $html[] = ' <div class="modal-footer">';
        $html[] = ' <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
        $html[] = ' <button type="button" class="btn btn-success js-areagroup-confirm" data-field-id="' . $hiddenId . '" data-ra-area-group-confirm disabled>'
            . 'Use Selection</button>';
        $html[] = ' </div>';
        $html[] = ' </div></div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Required by ListField, but not used for rendering here.
     */
    protected function getOptions()
    {
        return [];
    }

    private function getAreas(): array
    {
        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([$db->quoteName('code'), $db->quoteName('name')])
            ->from($db->quoteName('#__ra_areas'))
            ->order($db->quoteName('name') . ' ASC');

        $db->setQuery($query);

        $areas = $db->loadAssocList() ?: [];

        if ($areas !== []) {
            return $areas;
        }

        // Some installations have groups populated before the area table has
        // been refreshed. Derive the two-character area choices so the
        // selector remains usable.
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('code'))
            ->from($db->quoteName('#__ra_groups'))
            ->order($db->quoteName('code') . ' ASC');
        $db->setQuery($query);
        $areas = [];
        foreach ($db->loadColumn() ?: [] as $groupCode) {
            $areaCode = strtoupper(substr((string) $groupCode, 0, 2));
            if ($areaCode !== '' && !isset($areas[$areaCode])) {
                $areas[$areaCode] = ['code' => $areaCode, 'name' => 'Area ' . $areaCode];
            }
        }

        $areas = array_values($areas);
        usort($areas, static fn(array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return $areas;
    }

    private function getGroupLabel(string $groupCode): string
    {
        if ($groupCode === '') {
            return '';
        }

        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([$db->quoteName('code'), $db->quoteName('name')])
            ->from($db->quoteName('#__ra_groups'))
            ->where($db->quoteName('code') . ' = ' . $db->quote($groupCode));

        $db->setQuery($query);
        $row = $db->loadAssoc();

        return $row ? ($row['code'] . ' - ' . $row['name']) : $groupCode;
    }
}

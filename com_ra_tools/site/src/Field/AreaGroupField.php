
/components/com_yourcomponent/src/Field/AreaGroupField.php


<?php
/**
 * @package Joomla.Component
 */

namespace YourVendor\Component\Yourcomponent\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
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
            'com_t.areagroup-field',
            'media/com_yourcomponent/js/areagroup-field.js',
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
        $doc->addScriptOptions('areagroupField.' . $hiddenId, [
            'fieldId' => $hiddenId,
            'modalId' => $modalId,
            'areaId' => $areaId,
            'groupId' => $groupId,
            'labelId' => $labelId,
            'ajaxUrl' => 'index.php?option=com_ajax&plugin=plg_ra_ajaxgroups&format=json',
        ]);

        $html = [];

        // Hidden value that Joomla saves
        $html[] = '<input type="hidden"'
            . ' name="' . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8') . '"'
            . ' id="' . htmlspecialchars($hiddenId, ENT_QUOTES, 'UTF-8') . '"'
            . ' value="' . htmlspecialchars((string) $this->value, ENT_QUOTES, 'UTF-8') . '">';

        // Visible launcher + current selection
        $html[] = '<div class="d-flex align-items-center gap-2">';
        $html[] = ' <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#' . $modalId . '">'
            . Text::_('COM_YOURCOMPONENT_SELECT_AREA_GROUP') . '</button>';
        $html[] = ' <span id="' . $labelId . '" class="text-muted">'
            . htmlspecialchars($currentLabel ?: Text::_('JNONE'), ENT_QUOTES, 'UTF-8') . '</span>';
        $html[] = '</div>';

        // Modal
        $html[] = '<div class="modal fade" id="' . $modalId . '" tabindex="-1" aria-hidden="true">';
        $html[] = ' <div class="modal-dialog"><div class="modal-content">';
        $html[] = ' <div class="modal-header">';
        $html[] = ' <h5 class="modal-title">' . Text::_('COM_YOURCOMPONENT_SELECT_AREA_GROUP') . '</h5>';
        $html[] = ' <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . Text::_('JCLOSE') . '"></button>';
        $html[] = ' </div>';
        $html[] = ' <div class="modal-body">';

        $html[] = ' <div class="mb-3">';
        $html[] = ' <label for="' . $areaId . '" class="form-label">' . Text::_('COM_YOURCOMPONENT_AREA') . '</label>';
        $html[] = ' <select id="' . $areaId . '" class="form-select">';
        $html[] = ' <option value="">' . Text::_('COM_YOURCOMPONENT_SELECT_AREA') . '</option>';
        foreach ($areas as $area) {
            $code = (string) $area['code'];
            $name = (string) $area['name'];
            $html[] = ' <option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8')
                . '</option>';
        }
        $html[] = ' </select>';
        $html[] = ' </div>';

        $html[] = ' <div class="mb-3">';
        $html[] = ' <label for="' . $groupId . '" class="form-label">' . Text::_('COM_YOURCOMPONENT_GROUP') . '</label>';
        $html[] = ' <select id="' . $groupId . '" class="form-select" disabled>';
        $html[] = ' <option value="">' . Text::_('COM_YOURCOMPONENT_SELECT_GROUP') . '</option>';
        $html[] = ' </select>';
        $html[] = ' </div>';

        $html[] = ' </div>';
        $html[] = ' <div class="modal-footer">';
        $html[] = ' <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('JCANCEL') . '</button>';
        $html[] = ' <button type="button" class="btn btn-success js-areagroup-confirm" data-field-id="' . $hiddenId . '" disabled>'
            . Text::_('JSELECT') . '</button>';
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
            ->order($db->quoteName('code') . ' ASC');

        $db->setQuery($query);

        return $db->loadAssocList() ?: [];
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


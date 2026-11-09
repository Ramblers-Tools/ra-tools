<?php
/**
 * 25/10/26 CB Created
 */
namespace Ramblers\Component\Ra_tools\Site\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;

class Ra_contactField extends FormField
{
    protected $type = 'Ra_contact';

    protected function getInput()
    {
        $app = Factory::getApplication();
        $value = (int) $this->value;
        $fieldId = $this->id;

        $label = '';
        if ($value) {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select([$db->quoteName('p.preferred_name')])
                ->from($db->quoteName('#__ra_profiles', 'p'))
                ->where($db->quoteName('p.id') . ' = ' . (int) $value)
                ->setLimit(1);
            $db->setQuery($query);
            $label = (string) $db->loadResult();
            }

        $url = 'index.php?option=com_ra_tools&view=ra_users&layout=modal&tmpl=component&field=' . urlencode($fieldId);

        $html = '<div class="input-group">';
        $html .= '<input type="hidden" name="' . htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8') . '" id="' . htmlspecialchars($fieldId, ENT_COMPAT, 'UTF-8') . '" value="' . (int) $value . '" />';
        $html .= '<input type="text" id="' . htmlspecialchars($fieldId . '_name', ENT_COMPAT, 'UTF-8') . '" value="' . htmlspecialchars($label, ENT_COMPAT, 'UTF-8') . '" class="form-control" readonly />';
        $html .= '<button type="button" class="btn btn-primary" onclick="window.openRaUserModal = window.openRaUserModal || function() { const modalUrl = \'' . $url . '\'; const win = window.open(modalUrl, \'' . $fieldId . '_modal\', \'width=900,height=700,noopener,noreferrer\'); if (win) { win.focus(); } }; window.openRaUserModal();">Select</button>';
        $html .= '</div>';

        $html .= '<script>
            window.jSelectRaContact = function(id, title, fieldId) {
                var field = document.getElementById(fieldId);
                var fieldName = document.getElementById(fieldId + "_name");

                if (field) {
                    field.value = id;
                }

                if (fieldName) {
                    fieldName.value = title;
                }

                if (window.parent && window.parent.jQuery && window.parent.jQuery.fn.modal) {
                    window.parent.jQuery(".modal").modal("hide");
                }
            };
        </script>';

        return $html;
    }
}

<?php

namespace Ramblers\Component\Ra_tools\Site\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

/**
 * Modal user picker scoped to a configured Ramblers group.
 */
class RauserField extends FormField
{
    protected $type = 'rauser';

    protected function getInput(): string
    {
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $value = (int) $this->value;
        $fieldId = (string) $this->id;
        $modalId = 'ra-user-modal-' . substr(hash('sha256', $fieldId), 0, 12);
        $label = '';

        if ($value > 0) {
            $query = $db->getQuery(true)
                ->select('COALESCE(NULLIF(' . $db->quoteName('p.preferred_name') . ", ''), " . $db->quoteName('u.name') . ')')
                ->from($db->quoteName('#__users', 'u'))
                ->leftJoin($db->quoteName('#__ra_profiles', 'p') . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('u.id'))
                ->where($db->quoteName('u.id') . ' = ' . $value);
            $db->setQuery($query);
            $label = (string) $db->loadResult();
        }

        $groupCode = strtoupper(trim((string) ($this->element['group_code'] ?? '')));
        $secret = (string) Factory::getConfig()->get('secret', '');
        $currentUserId = (int) $app->getIdentity()->id;
        $canSelect = preg_match('/^[A-Z0-9]{2,4}$/', $groupCode) === 1
            && $secret !== ''
            && $app->getIdentity()->authorise('core.manage', 'com_users');
        $pickerUrl = '';
        $signature = '';

        if ($canSelect) {
            $signature = hash_hmac('sha256', $groupCode . '|' . $fieldId . '|' . $currentUserId, $secret);
            $query = http_build_query([
                'option' => 'com_ra_tools',
                'view' => 'ra_users',
                'layout' => 'modal',
                'tmpl' => 'component',
                'field' => $fieldId,
                'modal_id' => $modalId,
                'group_code' => $groupCode,
                'signature' => $signature,
            ]);
            $pickerUrl = rtrim(Uri::root(), '/') . '/administrator/index.php?' . $query;
            $app->getDocument()->getWebAssetManager()->useScript('bootstrap.modal');
        }

        $html = '<div class="input-group">';
        $html .= '<input type="hidden" name="' . $escape($this->name) . '" id="' . $escape($fieldId)
            . '" value="' . $value . '">';
        if ($canSelect) {
            $html .= '<input type="hidden" name="ra_contact[' . $escape($fieldId) . '][group_code]" value="'
                . $escape($groupCode) . '">';
            $html .= '<input type="hidden" name="ra_contact[' . $escape($fieldId) . '][signature]" value="'
                . $escape($signature) . '">';
        }
        $html .= '<input type="text" id="' . $escape($fieldId . '_name') . '" value="' . $escape($label)
            . '" class="form-control" readonly aria-label="' . $escape($this->label) . '">';

        if ($canSelect) {
            $html .= '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#'
                . $escape($modalId) . '">Select</button>';
        } else {
            $html .= '<span class="input-group-text">User selection unavailable</span>';
        }
        $html .= '</div>';

        if ($canSelect) {
            $html .= '<div class="modal fade" id="' . $escape($modalId) . '" tabindex="-1" aria-hidden="true">'
                . '<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">'
                . '<div class="modal-header"><h2 class="modal-title fs-5">Select a user</h2>'
                . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>'
                . '<div class="modal-body p-0"><iframe src="' . $escape($pickerUrl) . '" title="Select a user" '
                . 'style="width:100%;height:65vh;border:0" loading="lazy"></iframe></div>'
                . '</div></div></div>';
            $html .= '<script>
                window.jSelectRaContact = function(id, title, fieldId, modalId) {
                    var field = document.getElementById(fieldId);
                    var fieldName = document.getElementById(fieldId + "_name");
                    if (field) {
                        field.value = id;
                        field.dispatchEvent(new Event("change", { bubbles: true }));
                    }
                    if (fieldName) {
                        fieldName.value = title;
                    }
                    var modalElement = document.getElementById(modalId);
                    if (modalElement && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
                    }
                };
            </script>';
        }

        return $html;
    }
}

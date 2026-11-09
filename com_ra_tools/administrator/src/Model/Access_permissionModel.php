<?php

/**
 * Minimal model supplying the access-permission prototype form.
 */

namespace Ramblers\Component\Ra_tools\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;

class Access_permissionModel extends FormModel {

    public function getForm($data = [], $loadData = true) {
        return $this->loadForm(
                        'com_ra_tools.access_permission',
                        'access_permission',
                        [
                            'control' => 'jform',
                            'load_data' => $loadData,
                        ]
                ) ?: false;
    }

    protected function loadFormData() {
        $data = Factory::getApplication()->getUserState('com_ra_tools.edit.access_permission.data', []);

        if (empty($data)) {
            $data = [
                'id' => 0,
                'user' => 0,
                'access_to' => 0,
                'access_me' => 0,
                'access_ma' => 0,
            ];
        }

        return $data;
    }
}
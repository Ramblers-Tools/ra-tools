<?php

/**
 * Controller for managing component access user-group membership.
 * 02/11/26 CB Created
 */

namespace Ramblers\Component\Ra_tools\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Input\Input;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class Access_permissionController extends FormController {

    protected $app;
    protected $view_list = 'access_permissions';
    private $toolsHelper;

    public function __construct(
            $config = [],
            ?MVCFactoryInterface $factory = null,
            ?CMSApplication $app = null,
            ?Input $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);
        $this->app = Factory::getApplication();
        $this->toolsHelper = new ToolsHelper;
    }

    public function add($key = null, $urlVar = null): void {
        $this->setRedirect(Route::_('index.php?option=com_ra_tools&view=access_permission&layout=edit', false));
    }

    public function cancel($key = null, $urlVar = null): void {
        $this->setRedirect(Route::_('index.php?option=com_ra_tools&view=access_permissions', false));
    }

    public function cancelList(): void {
        $this->setRedirect(Route::_('index.php?option=com_ra_tools&view=dashboard', false));
    }

    public function save($key = null, $urlVar = null): void {
        $this->checkToken();

        if (!$this->ensureSuperuser()) {
            return;
        }

        $data = $this->app->input->post->get('jform', [], 'array');
        $userId = (int) ($data['user'] ?? 0);

        if ($userId <= 0) {
            $this->app->enqueueMessage('Please select a user.', 'error');
            $this->app->setUserState('com_ra_tools.edit.access_permission.data', $data);
            $this->setRedirect(Route::_('index.php?option=com_ra_tools&view=access_permission&layout=edit', false));

            return;
        }

        $results = [];
        $componentFields = [
            'com_ra_tools' => 'access_to',
            'com_ra_members' => 'access_me',
            'com_ra_mailman' => 'access_ma',
            'com_ra_events' => 'access_ev',
            'com_ra_treasurer' => 'access_tr',
        ];

        foreach ($componentFields as $component => $field) {
            if (ToolsHelper::isInstalled($component)) {
                $results[] = $this->setAccess($userId, $component, (int) ($data[$field] ?? 0) === 1);
            }
        }

        if (!in_array(false, $results, true)) {
            $this->app->enqueueMessage('Access permissions updated.', 'message');
        } else {
            $this->app->enqueueMessage('Failed to update one or more permissions.', 'warning');
        }

        $this->app->setUserState('com_ra_tools.edit.access_permission.data', null);
        $this->setRedirect(Route::_('index.php?option=com_ra_tools&view=access_permissions', false));
    }

    public function grant(): void {
        $this->checkToken();

        if (!$this->ensureSuperuser()) {
            return;
        }

        $userId = $this->app->input->getInt('user_id', 0);
        $component = $this->app->input->getCmd('component', '');

        if ($this->setAccess($userId, $component, true)) {
            $this->app->enqueueMessage('Access granted for ' . $component . '.', 'message');
        } else {
            $this->app->enqueueMessage('Unable to grant access for ' . $component . '.', 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ra_tools&view=access_permissions', false));
    }

    public function revoke(): void {
        $this->checkToken();

        if (!$this->ensureSuperuser()) {
            return;
        }

        $userId = $this->app->input->getInt('user_id', 0);
        $component = $this->app->input->getCmd('component', '');

        if ($this->setAccess($userId, $component, false)) {
            $this->app->enqueueMessage('Access revoked for ' . $component . '.', 'message');
        } else {
            $this->app->enqueueMessage('Unable to revoke access for the selected component.', 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ra_tools&view=access_permissions', false));
    }

    private function ensureSuperuser(): bool {
        if ($this->toolsHelper->isSuperuser()) {
            return true;
        }

        $this->app->enqueueMessage('Access denied.', 'error');
        $this->setRedirect(Route::_('index.php?option=com_ra_tools&view=dashboard', false));

        return false;
    }

    private function setAccess(int $userId, string $component, bool $enabled): bool {
        $groupId = $this->lookupGroupId($component);

        if ($userId <= 0 || $groupId <= 0) {
            $this->app->enqueueMessage( 'Usergroup for ' . $component . ' is not found.', 'error');
            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        if ($enabled) {
            $query->select('COUNT(*)')
                    ->from($db->quoteName('#__user_usergroup_map'))
                    ->where($db->quoteName('user_id') . ' = ' . $userId)
                    ->where($db->quoteName('group_id') . ' = ' . $groupId);
            $db->setQuery($query);

            if ((int) $db->loadResult() === 0) {
                $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__user_usergroup_map'))
                        ->columns($db->quoteName(['user_id', 'group_id']))
                        ->values($userId . ', ' . $groupId);
                $db->setQuery($query)->execute();
            }
        } else {
            $query->delete($db->quoteName('#__user_usergroup_map'))
                    ->where($db->quoteName('user_id') . ' = ' . $userId)
                    ->where($db->quoteName('group_id') . ' = ' . $groupId);
            $db->setQuery($query)->execute();
        }

        return true;
    }

    private function lookupGroupId(string $component): int {
                if (!in_array(
                        $component,
                        ['com_ra_tools', 'com_ra_events', 'com_ra_members', 'com_ra_mailman', 'com_ra_treasurer'],
                        true
                )) {
            $this->app->enqueueMessage( $component . ' is not valid.', 'error');
            return 0;
        }
        if (!ToolsHelper::isInstalled($component)) {
            $this->app->enqueueMessage( $component . ' is not installed.', 'error');
            return 0;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__usergroups'))
                ->where($db->quoteName('title') . ' = ' . $db->quote($component));
        $db->setQuery($query);

        return (int) $db->loadResult();
    }
}
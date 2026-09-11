<?php

/**
 * @version     5.1.0
 * @package     com_ra_tools
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie Bigley <webmaster@bigley.me.uk> - https://www.developer-url.com
 *
 * 14/08/26 CB Created
 * 07/09/26 CB improve reporting if unable to create a user
 */

namespace Ramblers\Component\Ra_tools\Site\Helpers;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\Table\Category;
use Joomla\Component\Contact\Administrator\Table\ContactTable;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_tools\Administrator\Table\ProfileTable;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class PersonHelper {

    protected $db;
    protected $toolsHelper;
    protected $userFactory;

    public function __construct() {
        $this->db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $this->toolsHelper = new ToolsHelper;
        $this->userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
    }

    public function addUserToGroup(int $userId, string $title): bool {
        $groupId = (int) $this->toolsHelper->getValue(
                        'SELECT id FROM #__usergroups WHERE title = ' . $this->db->quote($title) . ' LIMIT 1'
        );

        if ($groupId < 1) {
            return false;
        }

        $exists = (int) $this->toolsHelper->getValue(
                        'SELECT COUNT(*) FROM #__user_usergroup_map WHERE user_id = ' . $userId . ' AND group_id = ' . $groupId
                ) > 0;

        if (!$exists) {
            $mapping = (object) [
                        'user_id' => $userId,
                        'group_id' => $groupId,
            ];
            $this->db->insertObject('#__user_usergroup_map', $mapping);
        }

        return true;
    }

    public function getPerson(string $email): ?object {
        $email = strtolower(trim($email));
        $sql = 'SELECT u.id, u.name, u.email, p.home_group, p.preferred_name ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p ON p.id = u.id ';
        $sql .= 'WHERE LOWER(u.email) = ' . $this->db->quote($email) . ' ';
        $sql .= 'LIMIT 2';

        $person = $this->toolsHelper->getItem($sql);

        if ($person === false) {
            throw new \RuntimeException('Unable to find person by email: ' . $this->toolsHelper->error);
        }

        if ($this->toolsHelper->rows > 1) {
            throw new \RuntimeException(
                    'Unable to return one person because email ' . $email
                    . ' is linked to more than one profile.'
            );
        }

        return $person;
    }

    public function findUserByEmail(string $email): ?object {
        $email = strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        $user = $this->toolsHelper->getItem(
                'SELECT id, name, username, email, block FROM #__users WHERE LOWER(email) = '
                . $this->db->quote($email) . ' LIMIT 1'
        );

        if ($user === false) {
            throw new \RuntimeException('Unable to find user by email: ' . $this->toolsHelper->error);
        }

        return $user ?: null;
    }

    public function findUserById(int $userId): ?object {
        if ($userId < 1) {
            return null;
        }

        $user = $this->toolsHelper->getItem(
                'SELECT id, name, username, email, block FROM #__users WHERE id = ' . $userId . ' LIMIT 1'
        );

        if ($user === false) {
            throw new \RuntimeException('Unable to find user ' . $userId . ': ' . $this->toolsHelper->error);
        }

        return $user ?: null;
    }

    public function saveContact(int $userId, array $person, int $categoryId): void {
        $person['full_name'] = $this->normaliseName((string) ($person['full_name'] ?? ''));
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        Factory::getApplication()->bootComponent('com_contact');
        $contactId = (int) $this->toolsHelper->getValue(
                        'SELECT c.id FROM #__contact_details AS c '
                        . 'WHERE c.user_id = ' . $userId . ' AND c.catid = ' . $categoryId
                        . ' ORDER BY c.id LIMIT 1'
                );
        $contact = new ContactTable($db);

        if ($contactId > 0 && !$contact->load($contactId)) {
            throw new \RuntimeException('Unable to load the existing contact for ' . $person['full_name'] . '.');
        }

        if (!$contact->bind([
                    'name' => $person['full_name'],
                    'alias' => strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $person['full_name']))) . '-' . $userId,
                    'con_position' => implode('+', $person['roles']),
                    'user_id' => $userId,
                    'catid' => $categoryId,
                    'published' => 1,
                    'access' => 1,
                    'language' => '*',
                    'params' => '{}',
                    'metadata' => '{}',
                ]) || !$contact->check() || !$contact->store()) {
            throw new \RuntimeException('Unable to save the contact for ' . $person['full_name'] . ': ' . $contact->getError());
        }
    }

    public function saveProfile(int $userId, string $name, string $homeGroup): void {
        $name = $this->normaliseName($name);
        $homeGroup = strtoupper(trim($homeGroup));
        $this->saveProfileData($userId, [
            'preferred_name' => $name,
            'home_group' => $homeGroup,
            'state' => 1,
        ]);
    }

    public function profileExistsForUser(int $userId): bool {
        return (int) $this->toolsHelper->getValue(
                        'SELECT COUNT(*) FROM #__ra_profiles WHERE id = ' . $userId
                ) > 0;
    }

    public function saveProfileData(int $userId, array $data): void {
        if ($userId < 1) {
            throw new \InvalidArgumentException('A valid Joomla user ID is required to save a profile.');
        }

        $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('member_id'))
                ->from($this->db->quoteName('#__ra_profiles'))
                ->where($this->db->quoteName('id') . ' = ' . $userId)
                ->order($this->db->quoteName('member_id') . ' ASC');
        $this->db->setQuery($query);
        $profileIds = $this->db->loadColumn();

        if (count($profileIds) > 1) {
            throw new \RuntimeException(
                    'Unable to save a profile by Joomla user ID because user ' . $userId
                    . ' is linked to more than one profile.'
            );
        }

        $profile = new ProfileTable($this->db);

        if (!empty($profileIds) && !$profile->load((int) $profileIds[0])) {
            throw new \RuntimeException('Unable to load the existing profile for user ' . $userId . '.');
        }

        if (!$profile->bind([
                    'id' => $userId,
                ] + $data) || !$profile->check() || !$profile->store()) {
            throw new \RuntimeException(
                    'Unable to save the profile for user ' . $userId . ': ' . $profile->getError()
            );
        }
    }

    public function contactExists(int $userId, int $categoryId): bool {
        return (int) $this->toolsHelper->getValue(
                        'SELECT COUNT(*) FROM #__contact_details WHERE user_id = ' . $userId
                        . ' AND catid = ' . $categoryId
                ) > 0;
    }

    public function removeUserFromGroup(int $userId, string $title): bool {
        $groupId = (int) $this->toolsHelper->getValue(
                        'SELECT id FROM #__usergroups WHERE title = ' . $this->db->quote($title) . ' LIMIT 1'
                );

        if ($groupId < 1) {
            return false;
        }

        $query = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__user_usergroup_map'))
                ->where($this->db->quoteName('user_id') . ' = ' . $userId)
                ->where($this->db->quoteName('group_id') . ' = ' . $groupId);
        $this->db->setQuery($query)->execute();

        return true;
    }

    public function syncUserGroup(int $userId, string $title, bool $enabled): ?string {
        $groupId = (int) $this->toolsHelper->getValue(
                        'SELECT id FROM #__usergroups WHERE title = ' . $this->db->quote($title) . ' LIMIT 1'
                );

        if ($groupId < 1) {
            return null;
        }

        $exists = (int) $this->toolsHelper->getValue(
                        'SELECT COUNT(*) FROM #__user_usergroup_map WHERE user_id = ' . $userId
                        . ' AND group_id = ' . $groupId
                ) > 0;

        if ($enabled) {
            if ($exists) {
                return 'unchanged';
            }

            return $this->addUserToGroup($userId, $title) ? 'added' : false;
        }

        if (!$exists) {
            return 'unchanged';
        }

        return $this->removeUserFromGroup($userId, $title) ? 'removed' : false;
    }

    public function isEnabledSuperUser(int $userId): bool {
        return (int) $this->toolsHelper->getValue(
                        'SELECT COUNT(*) FROM #__users AS u '
                        . 'INNER JOIN #__user_usergroup_map AS m ON m.user_id = u.id '
                        . 'INNER JOIN #__usergroups AS g ON g.id = m.group_id '
                        . 'WHERE u.id = ' . $userId . ' AND u.block = 0 '
                        . 'AND g.title = ' . $this->db->quote('Super Users')
                ) > 0;
    }

    public function unpublishContacts(array $userIds, int $categoryId): array {
        $userIds = array_values(array_filter(array_map('intval', $userIds), static fn($id) => $id > 0));

        if ($userIds === []) {
            return [];
        }

        $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('name'))
                ->from($this->db->quoteName('#__contact_details'))
                ->where($this->db->quoteName('catid') . ' = ' . $categoryId)
                ->where($this->db->quoteName('published') . ' = 1')
                ->where($this->db->quoteName('user_id') . ' NOT IN (' . implode(',', $userIds) . ')');
        $names = $this->db->setQuery($query)->loadColumn();

        if ($names === []) {
            return [];
        }

        $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__contact_details'))
                ->set($this->db->quoteName('published') . ' = 0')
                ->where($this->db->quoteName('catid') . ' = ' . $categoryId)
                ->where($this->db->quoteName('published') . ' = 1')
                ->where($this->db->quoteName('user_id') . ' NOT IN (' . implode(',', $userIds) . ')');
        $this->db->setQuery($query)->execute();

        return array_map('strval', $names);
    }

    public function getOrCreateContactCategory(string $extension, string $title): int {
        $categoryId = (int) $this->toolsHelper->getValue(
                        'SELECT id FROM #__categories WHERE extension = ' . $this->db->quote($extension)
                        . ' AND LOWER(title) = ' . $this->db->quote(strtolower($title))
                        . ' ORDER BY id LIMIT 1'
                );

        if ($categoryId > 0) {
            return $categoryId;
        }

        $category = new Category($this->db);
        $category->setLocation(1, 'last-child');

        if (!$category->bind([
                    'parent_id' => 1,
                    'extension' => $extension,
                    'title' => $title,
                    'alias' => strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $title))),
                    'published' => 1,
                    'access' => 1,
                    'language' => '*',
                    'params' => '{}',
                    'metadata' => '{}',
                ]) || !$category->check() || !$category->store()) {
            throw new \RuntimeException('Unable to create the ' . $title . ' contact category: ' . $category->getError());
        }

        return (int) $category->id;
    }

    public function saveUser(string $name, string $email): int {
        $name = $this->normaliseName($name);
        $email = strtolower(trim($email));
        $sql = 'SELECT id FROM #__users WHERE LOWER(email) = ' . $this->db->quote($email) . ' LIMIT 1';
        $userId = (int) $this->toolsHelper->getValue($sql);
        $isNew = $userId < 1;
        $password = '';

        if ($userId > 0) {
            $user = $this->userFactory->loadUserById($userId);
            $data = ['name' => $name];
            $action = 'update';
        } else {
            $usernameOwner = (int) $this->toolsHelper->getValue(
                            'SELECT id FROM #__users WHERE LOWER(username) = ' . $this->db->quote($email) . ' LIMIT 1'
            );

            if ($usernameOwner > 0) {
                throw new \RuntimeException('The username ' . $email . ' is already used by another account.');
            }

            $user = $this->userFactory->loadUserById(0);
            $password = bin2hex(random_bytes(24));
            $data = [
                'name' => $name,
                'username' => $email,
                'email' => $email,
                'password' => $password,
                'password2' => $password,
                'block' => 0,
                'sendEmail' => 0,
                'registerDate' => Factory::getDate()->toSql(),
                'activation' => '',
                'params' => [],
                'requireReset' => 1,
            ];
            $action = 'create';
        }

        $saveRequired = $isNew || strcasecmp(trim((string) $user->name), $name) !== 0;

        if ($saveRequired) {
            try {
                if (!$user->bind($data)) {
                    $error = trim((string) $user->getError());
                    throw new \RuntimeException(
                            'Joomla rejected the user data.'
                            . ($error !== '' ? ' Details: ' . $error : '')
                    );
                }

                if (!$user->save()) {
                    $error = trim((string) $user->getError());
                    throw new \RuntimeException(
                            'Joomla was unable to ' . $action . ' the user.'
                            . ($error !== '' ? ' Details: ' . $error : '')
                    );
                }
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                                'Unable to ' . $action . ' the Joomla user for ' . $name . ': ' . $e->getMessage(),
                                0,
                                $e
                );
            }
        }

        if ((int) $user->id < 1) {
            throw new \RuntimeException('Joomla did not return a user ID for ' . $name . '.');
        }

        if ($isNew && !Factory::getApplication()->isClient('administrator')) {
            $this->sendNewUserEmail($name, $email, $password);
        }

        return (int) $user->id;
    }

    private function normaliseName(string $name): string {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            return '';
        }

        if (function_exists('mb_convert_case')) {
            $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

            return (string) preg_replace_callback(
                            "/([-\x{2019}'])(\p{L})/u",
                            static fn($match) => $match[1] . mb_strtoupper($match[2], 'UTF-8'),
                            $name
            );
        }

        return ucwords(strtolower($name), " \t\r\n\f\v-'");
    }

    public function sendNewUserEmail(string $name, string $email, string $password): void {
        $app = Factory::getApplication();
        $mailer = new MailTemplate('plg_user_joomla.mail', $app->getLanguage()->getTag());
        $mailer->addTemplateData([
            'name' => $name,
            'sitename' => $app->get('sitename'),
            'url' => Uri::root(),
            'username' => $email,
            'password' => $password,
            'email' => $email,
        ]);
        $mailer->addUnsafeTags(['username', 'password', 'name', 'email']);
        $mailer->addRecipient($email, $name);

        try {
            $sent = $mailer->send();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                            'Unable to send the Joomla new-user email to ' . $email . ': ' . $e->getMessage(),
                            0,
                            $e
            );
        }

        if ($sent === false) {
            throw new \RuntimeException('Unable to send the Joomla new-user email to ' . $email . '.');
        }
    }

}

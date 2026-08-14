<?php
/**
 * @version     5.1.0
 * @package     com_ra_tools
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie Bigley <webmaster@bigley.me.uk> - https://www.developer-url.com
 * 
 * 14/08/26 CB Created
 */
namespace Ramblers\Component\Ra_tools\Site\Helpers;

defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
class PersonHelper
{
    protected $app;
    protected $db;
    protected $toolsHelper;
    protected $userFactory;
    public function __construct()
    {
        $this->db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $this->toolsHelper = new ToolsHelper;
        $this->userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
    }
 
    private function addUserToGroup(int $userId, string $title): bool {
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


    private function saveContact(int $userId, array $person, int $categoryId): void {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        Factory::getApplication()->bootComponent('com_contact');
        $contactId = (int) $this->toolsHelper->getValue(
                        'SELECT c.id FROM #__contact_details AS c '
                        . 'INNER JOIN #__categories AS cat ON cat.id = c.catid '
                        . 'WHERE c.user_id = ' . $userId . ' AND cat.extension = "com_contact" '
                        . 'AND LOWER(cat.title) = "committee" ORDER BY c.id LIMIT 1'
        );
        $contact = new ContactTable($db);

        if ($contactId > 0 && !$contact->load($contactId)) {
            throw new \RuntimeException('Unable to load the existing contact for ' . $person['full_name'] . '.');
        }

        if (!$contact->bind([
                    'name' => $person['full_name'],
                    'alias' => strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $person['full_name']))) . '-' . $userId,
                    'con_position' => implode('+', $person['roles']),
                    'email_to' => $person['email'],
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

    private function saveProfile(int $userId, string $name, string $homeGroup): void {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $exists = (int) $this->toolsHelper->getValue('SELECT COUNT(*) FROM #__ra_profiles WHERE id = ' . $userId) > 0;
        $now = Factory::getDate()->toSql();
        $actorId = (int) Factory::getApplication()->getIdentity()->id;

        if ($exists) {
            $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ra_profiles'))
                    ->set($db->quoteName('home_group') . ' = ' . $db->quote($homeGroup))
                    ->set($db->quoteName('preferred_name') . ' = ' . $db->quote($name))
                    ->set($db->quoteName('state') . ' = 1')
                    ->set($db->quoteName('created') . ' = ' . $db->quote($now))
                    ->set($db->quoteName('created_by') . ' = ' . $actorId)
                    ->where($db->quoteName('id') . ' = ' . $userId);
            $db->setQuery($query)->execute();

            return;
        }

        $columns = $db->getTableColumns('#__ra_profiles', false);
        $record = (object) [
                    'id' => $userId,
                    'home_group' => $homeGroup,
                    'preferred_name' => $name,
                    'state' => 1,
                    'created' => $now,
                    'created_by' => $actorId,
        ];

        if (isset($columns['member_id']) && stripos((string) ($columns['member_id']->Extra ?? ''), 'auto_increment') === false) {
            $nextId = (int) $this->toolsHelper->getValue('SELECT COALESCE(MAX(member_id), 0) + 1 FROM #__ra_profiles');
            $record->member_id = max(1, $nextId);
        }

        $db->insertObject('#__ra_profiles', $record);
    }

    public function saveUser(string $name, string $email): int {
        $email = strtolower(trim($email));
        $sql = 'SELECT id FROM #__users WHERE LOWER(email) = ' . $this->db->quote($email) . ' LIMIT 1';
        $userId = (int) $this->toolsHelper->getValue($sql);
        $isNew = $userId < 1;
        $password = '';

        if ($userId > 0) {
            $user = $this->userFactory->loadUserById($userId);
            $data = ['name' => $name];
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
                    'params' => '{}',
                    'requireReset' => 1,
            ];
        }

        try {
            if (!$user->bind($data)) {
                throw new \RuntimeException('Joomla rejected the user data.');
            }

            if (!$user->save()) {
                throw new \RuntimeException('Joomla was unable to save the user.');
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                    'Unable to save the Joomla user for ' . $name . ': ' . $e->getMessage(),
                    0,
                    $e
            );
        }

        if ((int) $user->id < 1) {
            throw new \RuntimeException('Joomla did not return a user ID for ' . $name . '.');
        }

        if ($isNew && !Factory::getApplication()->isClient('administrator')) {
            $this->sendNewUserEmail($name, $email, $password);
        }

        return (int) $user->id;
    }

    private function sendNewUserEmail(string $name, string $email, string $password): void {
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

<?php
/*
* 16/09/26 CB created by Claude
* 16/09/26 CB added cascading delets for MailMan
*/
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Ramblers\Ra_tools\Site\Helpers\ToolsHelper;

class PlgSystemRaTools extends CMSPlugin {

    /**
     * Handle cascade deletion of related records when a user is deleted
     * 
     * @param   string  $context  The context for the content passed to the plugin
     * @param   object  $user     The user object being deleted
     * 
     * @return  boolean  True to allow deletion, false to prevent it
     */
    public function onUserBeforeDelete($context, $user): bool
    {
        $toolsHelper = new ToolsHelper();
        $userId = $user->id ?? 0;

        if ($userId <= 0) {
            return true;
        }

        try {
            // Delete related records from ra_emails table
            $sqlEmails = 'DELETE FROM #__ra_emails WHERE user_id = ' . (int)$userId;
            $toolsHelper->executeCommand($sqlEmails);

            // Delete related records from ra_profiles table
            $sqlProfiles = 'DELETE FROM #__ra_profiles WHERE user_id = ' . (int)$userId;
            $toolsHelper->executeCommand($sqlProfiles);
        } catch (Exception $e) {
            // Log the error but allow user deletion to continue
            JLog::add('Error deleting user related records: ' . $e->getMessage(), JLog::WARNING, 'com_ra_tools');
        }
        if ((ComponentHelper::isEnabled('com_ra_events', true))) {
            try {
                // Delete related records from ra_bookings table
                $sqlBookings = 'SELECT FROM #__ra_bookings WHERE user_id = ' . (int)$userId;
                $toolsHelper->executeCommand($sqlBookings);

                // Should delete related records from ra_guests table
//                $sqlGuests = 'DELETE FROM #__ra= ' . (int)$userId;
//                $toolsHelper->executeCommand($sqlProfiles);
            } catch (Exception $e) {
                // Log the error but allow user deletion to continue
                JLog::add('Error deleting user related records: ' . $e->getMessage(), JLog::WARNING, 'com_ra_tools');
            } 
        }        
        if ((ComponentHelper::isEnabled('com_ra_mailman', true))) {
            try {
                // Find any subscriptions for this user
                $sql = 'SELECT id, ';
                $sql .= 'WHERE user_id = ' . (int)$userId;
                $rows = $toolsHelper->getRows($sql);
                foreach ($rows as $row) {
                    $sql_audit = 'SELECT id FROM #__ra_mail_subscriptions_audit ';
                    $sql_audit .= 'WHERE object_id=' . $row->id;
                    $audit_rows = $toolsHelper->getRows($sql_audit);
                    foreach ($audit_rows as $audit_row) {
                        $sql = 'DELETE FROM #__ra_mail_subscriptions_audit ';
                        $sql .= 'WHERE object_id=' . $audit_row->id;
                        echo $sql . '<br>';
                        $toolsHelper->executeCommand($sql);
                    }

                    $sql = 'DELETE FROM  #__ra_mail_subscriptions ';
                    $sql .= 'WHERE id=' . $row->id;
                    echo $sql . '<br>';
                    $toolsHelper->executeCommand($sql);
                }
            } catch (Exception $e) {
                // Log the error but allow user deletion to continue
                JLog::add('Error deleting user related records: ' . $e->getMessage(), JLog::WARNING, 'com_ra_tools');
            } 
        }
        return true;
    }

}

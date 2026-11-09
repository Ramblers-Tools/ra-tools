<?php
/*
 * HousekeepingCommand.php
 *
 * This command handles routine housekeeping tasks for RA Tools, such as
 * removing expired log and booking guest records.
 * 09/11/26 CB Created
*/
namespace Ramblers\Plugin\Console\Ra_houskeeping\CliCommand;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseInterface;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class HousekeepingCommand extends AbstractCommand
{
    protected static $defaultName = 'ra_tools:housekeeping';
    protected $toolsHelper;

    protected function configure(): void
    {
        $this->setDescription('Remove expired RA Tools log and booking guest records.');
        $this->setHelp('Runs routine housekeeping for RA Tools data.');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $this->toolsHelper = new ToolsHelper();
        $offset = Factory::getConfig()->get('offset');
        $now = Factory::getDate('now', $offset);
        $logCutoff = clone $now;
        $logCutoff->modify('-90 days');
        $logCutoffSql = $logCutoff->toSql(true);
        $today = $now->format('Y-m-d');

        $logQuery = $db->getQuery(true)
            ->delete($db->quoteName('#__ra_logfile'))
            ->where($db->quoteName('log_date') . ' < ' . $db->quote($logCutoffSql));
        $db->setQuery($logQuery)->execute();
        $deletedLogs = $db->getAffectedRows();
        $this->sendMessage(sprintf('Deleted %d log record(s) older than 90 days.', $deletedLogs), $io);
        $io->text(sprintf('Deleted %d log record(s) older than 90 days.', $deletedLogs));

        if (!$this->isEventsComponentInstalled($db)) {
            $io->text('com_ra_events is not installed; booking guest cleanup skipped.');

            return 0;
        }

        $bookingGuestsTable = $db->replacePrefix('#__ra_booking_guests');
        $existingTables = array_map('strtolower', $db->getTableList());
        if (!in_array(strtolower($bookingGuestsTable), $existingTables, true)) {
            $io->text('The booking guests table is missing; cleanup skipped.');

            return 0;
        }

        $guestQuery = $db->getQuery(true)
            ->delete($db->quoteName('#__ra_booking_guests'))
            ->where($db->quoteName('expiry_date') . ' <= ' . $db->quote($today));
        $db->setQuery($guestQuery)->execute();
        $deletedGuests = $db->getAffectedRows();
        $this->sendMessage(sprintf('Deleted %d expired booking guest record(s).', $deletedGuests), $io);

        return 0;
    }

    private function isEventsComponentInstalled(DatabaseInterface $db): bool
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_ra_events'));

        return (int) $db->setQuery($query)->loadResult() > 0;
    }

    private function sendMessage(string $message, SymfonyStyle $io){
        $log_target = 'RA Tools';
        $io->text($message);
        $this->toolsHelper->createLog('RA Tools', '20', '20', $message);
    }
}

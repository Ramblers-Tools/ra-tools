<?php
namespace Ramblers\Component\Ra_tools\Site\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/** Site table adapter for the profile row keyed by Joomla user id. */
class Ra_profilesTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__ra_profiles', 'id', $db);
    }
}

<?php

/**
 * @version     3.4.2
 * @package     com_ra_tools
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie Bigley <webmaster@bigley.me.uk> - https://www.developer-url.com
 * 22/02/25 CB Created from com ramblers as LoadUsers
 * 04/03/25 CB Correct $this->getValue
 * 10/06/25 CB remove diagnostic, correct column/index counts
 * 13/10/25 CB optional start parameter for showTable
 */

namespace Ramblers\Component\Ra_tools\Site\Helpers;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use \Joomla\CMS\User\User;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

/**
 * Helper class to update database schema
 */
class SchemaHelper {

    protected $app;
    protected $objHelper;

    public function __construct() {
        $this->record_count = 0;
        $this->app = Factory::getApplication();
        $this->objHelper = new ToolsHelper;
    }

    function checkColumn($table, $column, $mode, $details = '') {
//  $mode = A: add the field, using data suppied in $details
//  $mode = U: update the field (keeping name the same), using $details
//  $mode = D: delete the field
        $table = trim($table);
        $column = trim($column);
        $count = $this->checkColumnExists($table, $column);
        $table_name = $this->dbPrefix . $table;
//       echo 'mode=' . $mode . ': Seeking ' . $table_name . '/' . $column . ', count=' . $count . "<br>";
        if (($mode == 'A') AND ($count == 1)
                OR ($mode == 'D') AND ($count == 0)) {
            return true;
        }
        if (($mode == 'U') AND ($count == 0)) {
            echo 'Field ' . $column . ' not found in ' . $table_name . '<br>';
            return false;
        }

        $sql = 'ALTER TABLE ' . $table_name . ' ';
        if ($mode == 'A') {
            $sql .= 'ADD ' . $column . ' ';
            $sql .= $details;
        } elseif ($mode == 'D') {
            $sql .= 'DROP ' . $column;
        } elseif ($mode == 'U') {
            $sql .= 'CHANGE ' . $column . ' ' . $column . ' ';
            $sql .= $details;
        }
        echo "$sql<br>";
        $response = $this->objHelper->executeCommand($sql);
        if ($response) {
            echo 'Success';
        } else {
            echo 'Failure';
        }
        echo ' for ' . $table_name . '<br>';
        return $count;
    }

    private function checkColumnExists($table, $column) {
        $config = Factory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $this->dbPrefix . $table . "' ";
        $sql .= "AND COLUMN_NAME='" . $column . "'";
//    echo "$sql<br>";

        return $this->objHelper->getValue($sql);
    }

    function checkTable($table, $details, $details2 = '') {

        $config = Factory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $table_name . "' ";
//        echo "$sql<br>";

        $count = $this->objHelper->getValue($sql);
        echo 'Seeking ' . $table_name . ', count = ' . $count . "<br>";
        if ($count > 0) {
            return $count;
        }
        $sql = 'CREATE TABLE ' . $table_name . ' ' . $details;
//        echo "$sql<br>";
        $response = $this->objHelper->executeCommand($sql);
        if ($response) {
            echo 'Table created OK<br>';
        } else {
            echo 'Failure<br>';
            return false;
        }
        if ($details2 != '') {
            $sql = 'ALTER TABLE ' . $table_name . ' ' . $details2;
            $response = $this->objHelper->executeCommand($sql);
            if ($response) {
                echo 'Table altered OK<br>';
            } else {
                echo 'Failure<br>';
                return false;
            }
        }
    }

    public function showSchema() {
        $config = Factory::getConfig();
        $database = $config->get('db');
        $dbPrefix = $config->get('dbprefix');
        $total_size = 0;
        $db = Factory::getContainer()->get('DatabaseDriver');

        $objTable = new ToolsTable();
        $objTable->add_header("Table, Record count, Column count, Index count, Data size, Index size, Total size MB");
        $sql = "SELECT TABLE_NAME, DATA_LENGTH, INDEX_LENGTH, ";
        $sql .= "ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS Size ";
        $sql .= "FROM information_schema.TABLES ";
        $sql .= "WHERE TABLE_SCHEMA = '" . $database . "' AND ";
        $sql .= "TABLE_NAME LIKE '" . $dbPrefix . "ra_%' ";
//        $sql .= " OR TABLE_NAME = '" . $dbPrefix . "users') ";
        $sql .= "ORDER BY TABLE_NAME";
//        echo $sql;
        /*
          UNION
          SELECT 'TOTALS:' AS 'TABLE_NAME',
          sum(DATA_LENGTH) AS 'DATA_LENGTH',
          sum(INDEX_LENGTH) AS 'INDEX_LENGTH',
          sum(data_length + INDEX_LENGTH) AS 'Size'";
          if (JDEBUG) {
          //           Factory::getApplication()->enqueueMessage($this->sql, 'notice');
          echo $sql;
          }
         */

        $tables = $this->objHelper->getRows($sql);
        foreach ($tables as $table) {
            $name = $db->quoteName($table->TABLE_NAME);
            $target = 'administrator/index.php?option=com_ra_tools&task=reports.showTableSchema&table=' . $name;
            $objTable->add_item($this->objHelper->buildLink($target, $table->TABLE_NAME));

            $sql2 = "SELECT COUNT(*) FROM " . $name;
            $target = 'administrator/index.php?option=com_ra_tools&task=reports.showTable&table=' . $table->TABLE_NAME;
            $count = $this->objHelper->getvalue($sql2);
            $objTable->add_item($this->objHelper->buildLink($target, number_format($count)));

            $sql2 = "SELECT COUNT(COLUMN_NAME) FROM information_schema.COLUMNS ";
            $sql2 .= "WHERE TABLE_NAME='" . $table->TABLE_NAME . "' ";
            $sql2 .= "AND TABLE_SCHEMA='" . $database . "' ";
//            echo $sql2 . '<br>';
            $objTable->add_item($this->objHelper->getvalue($sql2));

            $sql2 = "SELECT COUNT(INDEX_NAME) FROM information_schema.STATISTICS ";
            $sql2 .= "WHERE TABLE_SCHEMA='$database' AND TABLE_NAME='" . $table->TABLE_NAME . "'";
            $objTable->add_item($this->objHelper->getvalue($sql2));

            $objTable->add_item(number_format($table->DATA_LENGTH));
            $objTable->add_item(number_format($table->INDEX_LENGTH));
            $objTable->add_item($table->Size);
            $total_size = $total_size + $table->DATA_LENGTH + $table->INDEX_LENGTH;
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo 'Site ' . $this->app->get('sitename') . '<br>';
        echo 'Number of tables in ' . $database . ': ' . $objTable->num_rows . ', ';
        echo 'Total size: ' . $total_size / 1000 / 1000 . ' MB' . '<br>';
    }

    public function showTable($table, $limit = '10', $start = '0') {
        $config = Factory::getConfig();
        $database = $config->get('db');
        $prefix = $this->db->getPrefix();

        if (!str_starts_with($table, $prefix)) {
            throw new \InvalidArgumentException('Invalid table name');
        }

        $suffix = substr($table, strlen($prefix));

        if (!preg_match('/\A[A-Za-z0-9_]+\z/', $suffix)) {
            throw new \InvalidArgumentException('Invalid table name');
        }
//        ToolBarHelper::title($this->prefix . "$limit records from $database $table");
        $found_id = false;
        $sql = 'SELECT * FROM ' . '#__' . substr($table, strlen($suffix));
        if ($start > 0) {
            $sql .= ' WHERE id > ' . $start;
        }
        $sql .= ' LIMIT ' . $limit;
//        echo '#__' . substr($table, strlen($suffix)) . ': ' . strlen($suffix) . " $prefix $table<br> $sql<br>";
        $this->objHelper->showQuery($sql);

    }

    public function showTableSchema($table) {
        $config = Factory::getConfig();
        $database = $config->get('db');
//        $dbPrefix = $config->get('dbprefix');
        $objTable = new ToolsTable();

        $sql = "SELECT ORDINAL_POSITION,COLUMN_NAME,DATA_TYPE,IS_NULLABLE,";
        $sql .= "CHARACTER_MAXIMUM_LENGTH,COLUMN_KEY ";
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $table . "' ";
        $sql .= "ORDER BY ORDINAL_POSITION";
//        echo "$sql<br>";
        $objTable->add_header("Seq,Column name,Type,Max size,Null,Key");
        $columns = $this->objHelper->getRows($sql);

        foreach ($columns as $column) {
            $objTable->add_item(number_format($column->ORDINAL_POSITION));
            $objTable->add_item($column->COLUMN_NAME);
            $objTable->add_item($column->DATA_TYPE);
            $objTable->add_item($column->CHARACTER_MAXIMUM_LENGTH);
            $objTable->add_item($column->IS_NULLABLE);
            $objTable->add_item($column->COLUMN_KEY);
            $objTable->generate_line();
        }
        $objTable->generate_table();
    }

    function test() {
        echo __FILE__;
    }

}

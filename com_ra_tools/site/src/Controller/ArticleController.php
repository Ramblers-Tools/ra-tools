<?php

/**
 * 12/08/24 CB created
 * 16/08/24 copied to old.stokenewcastleramblers.org.uk as tools.showArticle
 * 04/09/24 CB add catid to local field list, only show link to original is logged in
 * 07/09/24 CB use criteria from model when seeking prev/next
 * 09/09/24 CB Show total number of Articles
 * 21/11/26 CB removed reference to getSession (J6 incompatible)
 */

namespace Ramblers\Component\Ra_tools\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\Database\DatabaseFactory;
use Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
use Joomla\Database\DatabaseInterface;

/**
 * Controller for a single article
 *
 * @since  1.6
 */
class ArticleController extends FormController {

    private function debug($code) {
        $objHelper = new ToolsHelper;
        $sql = "SELECT COUNT(id) FROM #__content  ";
//            echo $sql;
        $count_articles = $objHelper->getValue($sql);
        echo $sql . ' ' . $count_articles . '<br>';
    }

    public function showArticle() {
        $id = Factory::getApplication()->input->getInt('id', '0');
        if ($id == 0) {
            return false;
        }
//        echo "id=$id<br>";
        $menu_id = Factory::getApplication()->input->getInt('Itemid', '0');
        $menu = Factory::getApplication()->getMenu()->getActive();
        $objHelper = new ToolsHelper;

        if (is_null($menu)) {
            Factory::getApplication()->enqueueMessage('Database not specified', 'notice');
        } else {
            $params = $menu->getParams();
        }
        // Get details saved by the Model
        $criteria = Factory::getApplication()->getUserState('com_ra_tools.articles_criteria');
        $sort = Factory::getApplication()->getUserState('com_ra_tools.articles_sort', 'id');

        $sql = 'SELECT COUNT(a.id) FROM #__content AS a ';
        $sql .= 'LEFT JOIN #__users AS created ON created.id = a.created_by ';
        $sql .= 'LEFT JOIN #__users AS updated ON updated.id = a.modified_by ';
        $sql .= $criteria;

//       var_dump($params);
        if ($params->get('site') == 'remote') {
            Factory::getApplication()->enqueueMessage('Showing Article ' . $id . ' from ' . $params->get('remote_url'), 'notice');
            $config = array();
            $config['driver'] = 'mysqli';                   // Database driver name
            $config['host'] = $params->get('server');       // Database host name
            $config['database'] = $params->get('database'); // Database name
            $config['user'] = $params->get('user');         // User for database authentication
            $config['password'] = $params->get('password'); // Password for database authentication
            $config['prefix'] = $params->get('prefix');     // Database prefix
// external database connection
            $dbFactory = new DatabaseFactory();
//            echo 'dbFactory is of type ' . gettype($dbFactory) . '<br>';
//            echo 'class is ' . get_class($dbFactory) . '<br>';
//            var_dump($params);
//            die();
            try {
                $dbDriver = $dbFactory->getDriver('mysqli', $config);
            } catch (\RuntimeException $exception) {
                Factory::getApplication()->enqueueMessage(
                        Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()),
                        'warning'
                );
            }
            if (empty($dbDriver)) {
                throw new \RuntimeException("Unable to connect to the database");
            }
            $query = $dbDriver->getQuery(true);
            $query->select('a.title, a.introtext, a.fulltext, a.created, a.catid')
                    ->select('a.created_by, a.created_by_alias, created.name')
                    ->from($dbDriver->quoteName('#__content', 'a'))
                    ->leftJoin('#__users AS created ON created.id = a.created_by')
                    ->where('a.id = ' . $id);
            $dbDriver->setQuery($query);
//            Factory::getApplication()->enqueueMessage('sql = ' . $dbDriver->replacePrefix($query), 'notice');
//            echo 'sql = ' . $dbDriver->replacePrefix($query) . '<br>';
            $item = $dbDriver->loadObject();
        } else {
            $sql = 'SELECT a.title, a.introtext, a.fulltext, a.created, a.catid, ';
            $sql .= 'a.created_by, a.created_by_alias, created.name ';
            $sql .= 'FROM #__content AS a ';
            $sql .= 'LEFT JOIN #__users AS created ON created.id = a.created_by ';
            $sql .= 'WHERE a.id=' . $id;
            $item = $objHelper->getItem($sql);
        }

//      Find any more Articles, using criteria used by the model
//        echo "sort=$sort<br>";
        $sql = 'SELECT a.id FROM #__content AS a ';
        $sql .= 'LEFT JOIN #__users AS created ON created.id = a.created_by ';
        $sql .= 'LEFT JOIN #__users AS updated ON updated.id = a.modified_by ';
        $sql .= $criteria;
        if ($params->get('site') == 'remote') {
//            echo $sql . ' AND a.created>"' . $item->created . '" ORDER BY ' . $sort . ' ASC LIMIT 1' . '<br>';
            $dbDriver->setQuery($sql . ' AND a.created>"' . $item->created . '" ORDER BY ' . $sort . ' ASC LIMIT 1');
            $next_id = $dbDriver->loadResult();
            $dbDriver->setQuery($sql . ' AND a.created<"' . $item->created . '" ORDER BY ' . $sort . ' DESC LIMIT 1');
            $prev_id = $dbDriver->loadResult();
        } else {
            $next_id = $objHelper->getValue($sql . ' AND a.created>"' . $item->created . '" ORDER BY ' . $sort . ' ASC LIMIT 1');
            $prev_id = $objHelper->getValue($sql . ' AND a.created<"' . $item->created . '" ORDER BY ' . $sort . ' DESC LIMIT 1');
        }
//        var_dump($item);
        echo '<h2>' . $item->title . '</h2>';
//        echo strip_tags($item->introtext) . '<br><br>';
        $output = str_replace(
                '<img src="images',
                '<img src = "' . $params->get('remote_url') . '/images',
                $item->introtext . $item->fulltext);
        echo $output . '<br>';
        $message = 'Blog written ' . HTMLHelper::_('date', $item->created, 'H:i d M Y') . ' by ';
        if ($item->name == '') {
            $message .= 'user ' . $item->created_by;
        } else {
            $message .= $item->name;
        }
        if ($item->created_by_alias != '') {
            $message .= ', (on behalf of ' . $item->created_by_alias . ')';
        }
        echo $message . '<br>';

        $target = 'index.php?option=com_ra_tools&view=articles&Itemid=' . $menu_id;
        echo $objHelper->backButton($target);

        $target = "index.php?option = com_ra_tools&task=article.showArticle&Itemid=$menu_id&id=";
        if ($prev_id) {
            $prev = $objHelper->buildLink($target . $prev_id, "Prev", False, "link-button button-p0159");
            echo $prev;
        }
        if ($next_id) {
            $next = $objHelper->buildLink($target . $next_id, "Next", False, "link-button button-p0159");
            echo $next;
        }
        if ($params->get('site') == 'remote') {
            if (!Factory::getApplication()->getIdentity()->guest) {
                $target = $objHelper->addSlash($params->get('remote_url')) . 'index.php?option=com_content&view=article&id=' . $id;
                echo $objHelper->buildButton($target, "View original post", True, "teal");
            }
        }
        echo "<p>";
    }

}

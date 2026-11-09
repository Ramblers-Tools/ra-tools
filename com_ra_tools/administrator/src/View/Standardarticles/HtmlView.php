<?php

/**
 * 09/11/26 CB Replaced getDbo with DatabaseInterface, remove references to SidebarHelper
 */

namespace Ramblers\Component\Ra_tools\Administrator\View\Standardarticles;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\User\CurrentUserInterface;
use Joomla\CMS\Component\ComponentHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\JsonHelper;

/**
 * View to display a list of standard articles.
 *
 * @since  3.7.4
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $db;
    protected $items;
    protected $toolsHelper;
    protected $user;
    protected $site_name;
    protected $category_id;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches for the template in the layout directory
     *
     * @return  void
     */
    public function display($tpl = null) {
        $this->db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $this->user = $this->getCurrentUser();
        $this->toolsHelper = new ToolsHelper;

        $params = ComponentHelper::getParams('com_ra_tools');
        $site_id = $params->get('site_id');
        $this->category_id = $params->get('category_id', 0); // Default to 0 if not set
        $endpoint = '/api/index.php/v1/content/articles';
        if ($this->category_id > 0) {
            $endpoint .= '?filter[category]=' . $this->category_id;
        }
        $sql = 'SELECT title FROM #__ra_api_sites where id=' . (int) $site_id;
        $this->site_name = $this->toolsHelper->getValue($sql);
        $this->items = JsonHelper::getRemoteData($site_id, $endpoint);

        if ($this->items === false) {
            $app = Factory::getApplication();
            $messages = JsonHelper::getMessages();
            foreach ($messages as $message) {
                $app->enqueueMessage($message, 'error');
            }
            $this->items = [];
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar() {
        $state = $this->get('State');
        $canDo = ToolsHelper::getActions('com_ra_tools');

        ToolbarHelper::title('Standard Articles', 'generic');

        $toolbar = Toolbar::getInstance('toolbar');
        /*
          $toolbar->standardButton('nrecords')
          ->icon('fa fa-info-circle')
          ->text(number_format($this->pagination->total) . ' Records')
          ->task('')
          ->onclick('return false')
          ->listCheck(false);
         */
        ToolbarHelper::cancel('standardarticles.cancel', 'Return to Dashboard');
    }

    public function getLocal($title) {
        // Seeks Article of the same name in the local database
        $sql = 'SELECT id, modified FROM #__content ';
        $sql .= 'WHERE title=' . $this->db->quote($title);
        return $this->toolsHelper->getItem($sql);
    }

}

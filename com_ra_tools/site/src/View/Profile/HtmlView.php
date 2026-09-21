<?php

/**
 * @version    3.2.1
 * @package    com_ra_tools
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 27/04/24 CB Add access view
 * 16/12/24 CB use getIdentity, not getUser
 * 08/04/25 CB CurrentUserInterface
 * 03/05/25 CB use toolsHelper->showAccess
 */

namespace Ramblers\Component\Ra_tools\Site\View\Profile;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\User\CurrentUserInterface;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $item;
    protected $form;
    protected $params;
    protected $toolsHelper;
    protected $user;

    public function display($tpl = null) {
        $layout = Factory::getApplication()->input->getCmd('layout', '');
        $this->user = $this->getCurrentUser();
        $this->params = ComponentHelper::getParams('com_ra_tools');
        $this->params = $this->get('Params');
        if ($this->user->id == 0) {
            Factory::getApplication()->enqueueMessage('Please login to gain access to this function', 'error');
            return false;
        }
        //       }
        $this->item = $this->get('Item');
        if (!$this->item) {
            throw new \RuntimeException('Your profile could not be found.', 404);
        }
        $model = $this->getModel();
        $this->toolsHelper = new ToolsHelper;
        return parent::display($tpl);
    }

}

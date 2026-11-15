<?php

/**
 * The pages for signing in to the Ramblers Tools app (see Controller/AppController.php):
 * login, consent, error and signins. The controller sets what each page shows.
 *
 * 14/11/26 RH Created
 */

namespace Ramblers\Component\Ra_tools\Site\View\App;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView {

    public $message = '';
    public $loginUrl = '';
    public $ssoUrl = null;
    public $params = [];
    public $name = '';
    public $logoutUrl = '';
    public $signIns = [];

    public function display($tpl = null) {
        $document = Factory::getApplication()->getDocument();
        $document->setTitle('Ramblers Tools app');
        // Nothing on these pages belongs in a search engine.
        $document->setMetaData('robots', 'noindex, nofollow');
        return parent::display($tpl);
    }
}

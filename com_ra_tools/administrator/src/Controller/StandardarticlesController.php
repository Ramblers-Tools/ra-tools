<?php
/**
 * @version    3.7.4
 * @package    com_ra_tools
 * @author     GitHub Copilot
 * @copyright  2026
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 10/08/26 CB Use Joomla's workflow when creatiing artices, not just the table
 */
namespace Ramblers\Component\Ra_tools\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Workflow\Workflow;
use Ramblers\Component\Ra_tools\Site\Helpers\JsonHelper;

/**
 * Standardarticles Controller
 *
 * @since  3.7.4
 */
class StandardarticlesController extends BaseController
{
    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'standardarticles';

    protected $db;

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    public function refresh()
    {
        $this->db = Factory::getDbo();
        $input = $this->app->input;
        $remote_id    = $input->getInt('remote_id');
        $local_id = $input->getInt('local_id');
        $params = ComponentHelper::getParams('com_ra_tools');
        $api_site_id = $params->get('site_id');
    // Get the remote data for the article
    
        $endpoint = $params->get('api_endpoint');
        $endpoint .= '/api/index.php/v1/content/articles/'  . $remote_id;   
        $jsonHelper = new JsonHelper();
        $item = $jsonHelper->getRemoteData($api_site_id, $endpoint );

        if (!$item) {
            $this->app->enqueueMessage('Failed to retrieve remote article.', 'error');
            $messages = $jsonHelper->getMessages();
            foreach($messages as $message){
                $this->app->enqueueMessage($message, 'error');
            }
            $this->setRedirect(Route::_('index.php?option=com_ra_tools&view=standardarticles', false));
            return;
        }

        $data = [
            'id' => $local_id,
            'title' => $item->title,
            'articletext' => $item->text,
            'catid' => $params->get('local_category_id'),
            'state' => 1, // Published
            'language' => '*',
        ];

        $contentComponent = $this->app->bootComponent('com_content');
        $articleModel = $contentComponent->getMVCFactory()->createModel(
            'Article',
            'Administrator',
            ['ignore_request' => true]
        );

        if ($articleModel->save($data)) {
            $articleId = (int) $articleModel->getState('article.id');
            $storedArticle = $articleModel->getItem($articleId);

            if ($articleId > 0 && $storedArticle) {
                // Older imports saved directly to #__content and may not have
                // the workflow association required by Joomla's article list.
                $workflow = new Workflow('com_content.article', $this->app, $this->db);
                $workflowAssociation = $workflow->getAssociation($articleId);

                if (!$workflowAssociation) {
                    $stageId = (int) $workflow->getDefaultStageByCategory((int) $storedArticle->catid);

                    if ($stageId < 1 || !$workflow->createAssociation($articleId, $stageId)) {
                        $this->app->enqueueMessage(
                            'Article saved, but its Joomla workflow association could not be created. ID: '
                                . $articleId,
                            'error'
                        );
                        $this->setRedirect(Route::_('index.php?option=com_ra_tools&view=standardarticles', false));
                        return;
                    }
                }

                $articleTitle = htmlspecialchars(
                    (string) $storedArticle->title,
                    ENT_QUOTES,
                    'UTF-8'
                );
            } else {
                $this->app->enqueueMessage(
                    'Article saved, but the stored record could not be retrieved. ID: ' . $articleId,
                    'warning'
                );
            }
        } else {
            $this->app->enqueueMessage('Error saving article: ' . $articleModel->getError(), 'error');
        }
        $this->setRedirect(Route::_('index.php?option=com_ra_tools&view=standardarticles', false));
    }
}

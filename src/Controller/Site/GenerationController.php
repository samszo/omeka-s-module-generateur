<?php
namespace Generateur\Controller\Site;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class GenerationController extends AbstractActionController
{
    public function browseAction()
    {
        $site = $this->currentSite();

        $this->setBrowseDefaults('created');

        $query = $this->params()->fromQuery();
        $query['site_id'] = $site->id();

        $response = $this->api()->search('generations', $query);
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $resources = $response->getContent();

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('resources', $resources);
        $view->setVariable('generations', $resources);
        return $view;
    }

    public function showAction()
    {
        $site = $this->currentSite();
        $response = $this->api()->read('generations', $this->params('id'));

        $view = new ViewModel;
        $resource = $response->getContent();
        $view->setVariable('site', $site);
        $view->setVariable('resource', $resource);
        $view->setVariable('generation', $resource);
        return $view;
    }
}

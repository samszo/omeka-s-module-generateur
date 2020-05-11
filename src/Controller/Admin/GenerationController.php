<?php
namespace Generateur\Controller\Admin;

use Generateur\Entity\Generation;
use Generateur\Form\GenerateurForm;
use Generateur\Form\QuickSearchForm;
use Generateur\Form\ResourceForm;
use Omeka\Form\ConfirmForm;
use Omeka\Mvc\Exception\NotFoundException;
use Omeka\Stdlib\Message;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

class GenerationController extends AbstractActionController
{
    public function searchAction()
    {
        $view = new ViewModel;
        $view->setVariable('query', $this->params()->fromQuery());
        return $view;
    }

    public function browseAction()
    {
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('generations', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $formSearch = $this->getForm(QuickSearchForm::class);
        $formSearch->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'browse'], true));
        $formSearch->setAttribute('id', 'generation-search');
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $formSearch->setData($data);
        } elseif ($this->getRequest()->isGet()) {
            $data = $this->params()->fromQuery();
            $formSearch->setData($data);
        }

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute('admin/generation/default', ['action' => 'batch-delete'], true));
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute('admin/generation/default', ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $view = new ViewModel;
        $resources = $response->getContent();
        $view->setVariable('resources', $resources);
        $view->setVariable('generations', $resources);
        $view->setVariable('formSearch', $formSearch);
        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('formDeleteAll', $formDeleteAll);
        return $view;
    }

    public function showAction()
    {
        $response = $this->api()->read('generations', $this->params('id'));

        $view = new ViewModel;
        $resource = $response->getContent();
        $view->setVariable('resource', $resource);
        $view->setVariable('generation', $resource);
        return $view;
    }

    public function showDetailsAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('generations', $this->params('id'));
        $resource = $response->getContent();
        $values = $resource->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('resource', $resource);
        $view->setVariable('values', json_encode($values));
        return $view;
    }

    // TODO Make possible to add an generation directly (not only ajax or specialized generation)?

    /**
     * Generateur a resource.
     *
     * Equivalent to action "add", but without specific page (so via ajax).
     */
    public function generateAction()
    {
        $redirect = $this->params()->fromQuery('redirect');

        $isAjax = $this->getRequest()->isXmlHttpRequest();
        if (!$redirect && !$isAjax) {
            $this->messenger()->addError('Only a resource can be generated.'); // @translate
            $urlHelper = $this->viewHelpers()->get('url');
            return $this->redirect()->toUrl($urlHelper('admin'));
        }

        $isPost = $this->getRequest()->isPost();
        if (!$isPost) {
            if ($isAjax) {
                return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
            }
            $this->messenger()->addError('Unauthorized access.'); // @translate
            return $this->redirect()->toUrl($redirect);
        }

        // TODO Move validation inside form and adapter.
        $form = $this->getForm(GenerateurForm::class);
        $data = $this->params()->fromPost();

        $resourceId = $data['o:resource']['o:id'];
        if (empty($resourceId)) {
            if ($isAjax) {
                return $this->jsonError('Resource not found.', Response::STATUS_CODE_404); // @translate
            } else {
                $this->messenger()->addError('Resource not found.'); // @translate
                return $this->redirect()->toUrl($redirect);
            }
        }

        $api = $this->api();

        $resource = $api->read('resources', ['id' => $resourceId])->getContent();
        if (!$resource) {
            if ($isAjax) {
                return $this->jsonError('Resource not found.', Response::STATUS_CODE_404); // @translate
            } else {
                $this->messenger()->addError('Resource not found'); // @translate
                return $this->redirect()->toUrl($redirect);
            }
        }

        $form->setData($data);
        if (!$form->isValid()) {
            if ($isAjax) {
                return $this->jsonError($form->getMessages());
            } else {
                $this->messenger()->addFormErrors($form);
                return $this->redirect()->toUrl($redirect);
            }
        }

        // TODO Check of data form is currently not available.
        // $data = $form->getData();

        $resourceTemplate = $api->searchOne('resource_templates', ['label' => 'Génération'])->getContent();
        if (!$resourceTemplate) {
            if ($isAjax) {
                return $this->jsonError('Resource template "Génération" not found.', Response::STATUS_CODE_404); // @translate
            } else {
                $this->messenger()->addError('Resource template "Génération" not found'); // @translate
                return $this->redirect()->toUrl($redirect);
            }
        }
        $data['o:resource_template']['o:id'] = $resourceTemplate->id();

        // The form contains errors if any.
        $response = $this->api($form)->create('generations', $data);
        if (!$response) {
            if ($isAjax) {
                return new JsonModel([
                    'error' => $form->getMessages(),
                ]);
            } else {
                return $this->redirect()->toUrl($redirect);
            }
        }

        $generation = $response->getContent();

        if ($isAjax) {
            return new JsonModel([
                'content' => [
                    'resource_id' => $resourceId,
                    'generation' => $generation->getJsonLd(),
                    'moderation' => !$this->userIsAllowed(Generation::class, 'update'),
                ],
            ]);
        }

        $message = new Message(
            'Generation #%1$d successfully generated for resource #%2$d.', // @translate
            $generation->id(), $resourceId
        );
        $this->messenger()->addSuccess($message);
        return $this->redirect()->toUrl($redirect);
    }

    public function editAction()
    {
        $form = $this->getForm(ResourceForm::class);

        $response = $this->api()->read('generations', $this->params('id'));
        $resource = $response->getContent();

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('generation', $resource);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $data = $this->params()->fromPost();
        if (isset($data['values_json']) && $valuesJson = json_decode($data['values_json'], true)) {
            $data = array_merge_recursive($data, $valuesJson);
            unset($data['values_json']);
        }

        $form->setData($data);
        if (!$form->isValid()) {
            $this->messenger()->addFormErrors($form);
            return $view;
        }

        // TODO Make data available from the form.
        // $data = $form->getData();
        $data['o:resource']['o:id'] = $resource->resource()->id();
        $response = $this->api($form)->update('generations', $resource->id(), $data);
        if (!$response) {
            return $view;
        }

        $this->messenger()->addSuccess('Generation successfully updated'); // @translate
        return $this->redirect()->toUrl($response->getContent()->url());
    }

    public function deleteConfirmAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('generations', $this->params('id'));
        $resource = $response->getContent();
        $values = $resource->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resource', $resource);
        $view->setVariable('resourceLabel', 'generation');
        $view->setVariable('partialPath', 'generateur/admin/generation/show-details');
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('generation', $resource);
        $view->setVariable('values', json_encode($values));

        // With a redirect, the Omeka view helper deleteConfirm cannot be used.
        $redirect = $this->params()->fromQuery('redirect');
        if ($redirect) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setAttribute('action', $resource->url('delete') . '?' . http_build_query(['redirect' => $redirect]));
            $view->setVariable('form', $form);
            $view->setTemplate('generateur/admin/generation/delete-confirm-redirect');
        }

        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('generations', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Generation successfully deleted.'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $redirect = $this->params()->fromQuery('redirect');
        return $redirect
            ? $this->redirect()->toUrl($redirect)
            : $this->redirect()->toRoute('admin/generation');
    }

    public function batchDeleteConfirmAction()
    {
        $form = $this->getForm(ConfirmForm::class);
        $routeAction = $this->params()->fromQuery('all') ? 'batch-delete-all' : 'batch-delete';
        $form->setAttribute('action', $this->url()->fromRoute(null, ['action' => $routeAction], true));
        $form->setButtonLabel('Confirm delete'); // @translate
        $form->setAttribute('id', 'batch-delete-confirm');
        $form->setAttribute('class', $routeAction);

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('form', $form);
        return $view;
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one generation to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('generations', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Generations successfully deleted.'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function batchDeleteAllAction()
    {
        $this->messenger()->addError('Delete of all generations is not supported currently.'); // @translate
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    /**
     * Return the generation settings for a specific resource template (ajax only).
     */
    public function resourceTemplateDataAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }
        $resourceTemplateId = $this->params()->fromQuery('resource_template_id');
        $generationPartMap = $this->resourceTemplateGenerationPartMap($resourceTemplateId);
        $result = [];
        $api = $this->api();
        foreach ($generationPartMap as $term => $value) {
            $property = $api->searchOne('properties', ['term' => $term])->getContent();
            if ($property) {
                $result[$property->id()] = $value;
            }
        }
        return new JsonModel($result);
    }

    protected function jsonError($message, $statusCode = Response::STATUS_CODE_500)
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        return new JsonModel([
            'status' => 'error',
            'message' => $message,
        ]);
    }
}

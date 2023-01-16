<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau, 2017-2020
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Generateur;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generateur\Entity\Generation;
use Generateur\Permissions\Acl;
use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Permissions\Acl\Acl as LaminasAcl;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\UserRepresentation;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        // TODO Add filters (don't display when resource is private, like media?).
        // TODO Set Acl public rights to false when the visibility filter will be ready.
        // $this->addEntityManagerFilters();
        $this->addAclRoleAndRules();
    }

    protected function preInstall():void
    {
        $services = $this->getServiceLocator();
        $module = $services->get('Omeka\ModuleManager')->getModule('Generic');
        if ($module && version_compare($module->getIni('version'), '3.0.18', '<')) {
            $translator = $services->get('MvcTranslator');
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('This module requires the module "%s", version %s or above.'), // @translate
                'Generic', '3.0.18'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException($message);
        }
    }

    protected function postUninstall():void
    {
        $services = $this->getServiceLocator();

        if (!class_exists(\Generic\InstallResources::class)) {
            require_once file_exists(dirname(__DIR__) . '/Generic/InstallResources.php')
                ? dirname(__DIR__) . '/Generic/InstallResources.php'
                : __DIR__ . '/src/Generic/InstallResources.php';
        }

        $installResources = new \Generic\InstallResources($services);
        $installResources = $installResources();

        if (!empty($_POST['remove-template'])) {
            $resourceTemplate = 'Génération';
            $installResources->removeResourceTemplate($resourceTemplate);
        }
    }

    public function warnUninstall(Event $event): void
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() != __NAMESPACE__) {
            return;
        }

        $serviceLocator = $this->getServiceLocator();
        $t = $serviceLocator->get('MvcTranslator');

        $resourceTemplates = 'Génération';

        $html = '<p>';
        $html .= '<strong>';
        $html .= $t->translate('WARNING'); // @translate
        $html .= '</strong>' . ': ';
        $html .= '</p>';

        $html .= '<p>';
        $html .= $t->translate('All the generations will be removed.'); // @translate
        $html .= '</p>';

        $html .= '<p>';
        $html .= sprintf(
            $t->translate('If checked, the resource templates "%s" will be removed too. The resource template of the resources that use it will be reset.'), // @translate
            $resourceTemplates
        );
        $html .= '</p>';
        $html .= '<label><input name="remove-template" type="checkbox" form="confirmform">';
        $html .= sprintf($t->translate('Remove the resource templates "%s"'), $resourceTemplates); // @translate
        $html .= '</label>';

        echo $html;
    }

    /**
     * Add ACL role and rules for this module.
     */
    protected function addAclRoleAndRules(): void
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        // Since Omeka 1.4, modules are ordered, so Guest come after Generateur.
        // See \Guest\Module::onBootstrap().
        if (!$acl->hasRole('guest')) {
            $acl->addRole('guest');
        }

        $settings = $services->get('Omeka\Settings');
        // TODO Set rights to false when the visibility filter will be ready.
        // TODO Check if public can generate and flag, and read generations and own ones.
        $publicViewGeneration = $settings->get('generateur_public_allow_view', true);
        if ($publicViewGeneration) {
            $publicAllowGenerate = $settings->get('generateur_public_allow_generate', false);
            if ($publicAllowGenerate) {
                $this->addRulesForVisitorCreators($acl);
            } else {
                $this->addRulesForVisitors($acl);
            }
        }

        // Identified users can generateur. Reviewer and above can approve. Admins
        // can delete.
        $this->addRulesForCreators($acl);
        $this->addRulesForApprobators($acl);
        $this->addRulesForAdmins($acl);
    }

    /**
     * Add ACL rules for visitors (read only).
     *
     * @todo Add rights to update generation (flag only).
     *
     * @param LaminasAcl $acl
     */
    protected function addRulesForVisitors(LaminasAcl $acl): void
    {
        $acl
            ->allow(
                null,
                [Generation::class],
                ['read']
            )
            ->allow(
                null,
                [Api\Adapter\GenerationAdapter::class],
                ['search', 'read']
            )
            ->allow(
                null,
                [Controller\Site\GenerationController::class],
                ['index', 'browse', 'show', 'search', 'flag']
            );
    }

    /**
     * Add ACL rules for visitors who can generate.
     *
     * @param LaminasAcl $acl
     */
    protected function addRulesForVisitorCreators(LaminasAcl $acl): void
    {
        $acl
            ->allow(
                null,
                [Generation::class],
                ['read', 'create']
            )
            ->allow(
                null,
                [Api\Adapter\GenerationAdapter::class],
                ['search', 'read', 'create']
            )
            ->allow(
                null,
                [Controller\Site\GenerationController::class],
                ['index', 'browse', 'show', 'search', 'add', 'flag']
            );
    }

    /**
     * Add ACL rules for users who can generate (not visitor).
     *
     * @param LaminasAcl $acl
     */
    protected function addRulesForCreators(LaminasAcl $acl): void
    {
        $roles = [
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
        ];
        $acl
            ->allow(
                $roles,
                [Generation::class],
                ['create']
            )
            ->allow(
                $roles,
                [Generation::class],
                ['update', 'delete'],
                new \Omeka\Permissions\Assertion\OwnsEntityAssertion
            )
            ->allow(
                $roles,
                [Api\Adapter\GenerationAdapter::class],
                ['search', 'read', 'create', 'update', 'delete', 'batch_create', 'batch_update', 'batch_delete']
            )
            ->allow(
                $roles,
                [Controller\Site\GenerationController::class]
            )
            ->allow(
                $roles,
                [Controller\Admin\GenerationController::class],
                ['index', 'search', 'browse', 'show', 'show-details', 'add', 'edit', 'delete', 'delete-confirm', 'flag']
            );
    }

    /**
     * Add ACL rules for reviewers and editors (approbators).
     *
     * @param LaminasAcl $acl
     */
    protected function addRulesForApprobators(LaminasAcl $acl): void
    {
        // Admin are approbators too, but rights are set below globally.
        $roles = [
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
        ];
        // "view-all" is added via main acl factory for resources.
        $acl
            ->allow(
                [\Omeka\Permissions\Acl::ROLE_REVIEWER],
                [Generation::class],
                ['read', 'create', 'update']
            )
            ->allow(
                [\Omeka\Permissions\Acl::ROLE_REVIEWER],
                [Generation::class],
                ['delete'],
                new \Omeka\Permissions\Assertion\OwnsEntityAssertion
            )
            ->allow(
                [\Omeka\Permissions\Acl::ROLE_EDITOR],
                [Generation::class],
                ['read', 'create', 'update', 'delete']
            )
            ->allow(
                $roles,
                [Api\Adapter\GenerationAdapter::class],
                ['search', 'read', 'create', 'update', 'delete', 'batch_create', 'batch_update', 'batch_delete']
            )
            ->allow(
                $roles,
                [Controller\Site\GenerationController::class]
            )
            ->allow(
                $roles,
                Controller\Admin\GenerationController::class,
                [
                    'index',
                    'search',
                    'browse',
                    'show',
                    'show-details',
                    'add',
                    'edit',
                    'delete',
                    'delete-confirm',
                    'flag',
                    'batch-approve',
                    'batch-unapprove',
                    'batch-flag',
                    'batch-unflag',
                    'batch-set-spam',
                    'batch-set-not-spam',
                    'toggle-approved',
                    'toggle-flagged',
                    'toggle-spam',
                    'batch-delete',
                    'batch-delete-all',
                    'batch-update',
                    'approve',
                    'unflag',
                    'set-spam',
                    'set-not-spam',
                    'show-details',
                ]
            );
    }

    /**
     * Add ACL rules for approbators.
     *
     * @param LaminasAcl $acl
     */
    protected function addRulesForAdmins(LaminasAcl $acl): void
    {
        $roles = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
        ];
        $acl
            ->allow(
                $roles,
                [
                    Generation::class,
                    Api\Adapter\GenerationAdapter::class,
                    Controller\Site\GenerationController::class,
                    Controller\Admin\GenerationController::class,
                ]
            );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Add the Generation to the representation.
        $representations = [
            // 'users' => UserRepresentation::class,
            // 'item_sets' => ItemSetRepresentation::class,
            'items' => ItemRepresentation::class,
            // 'media' => MediaRepresentation::class,
        ];
        foreach ($representations as $representation) {
            $sharedEventManager->attach(
                $representation,
                'rep.resource.json',
                [$this, 'filterJsonLd']
            );
        }

        // TODO Add the special data to the resource template.

        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'addHeadersAdmin']
        );

        // Manage the search query with special fields that are not present in
        // default search form.
        $sharedEventManager->attach(
            \Generateur\Controller\Admin\GenerationController::class,
            'view.advanced_search',
            [$this, 'displayAdvancedSearchGeneration']
        );
        // Filter the search filters for the advanced search pages.
        $sharedEventManager->attach(
            \Generateur\Controller\Admin\GenerationController::class,
            'view.search.filters',
            [$this, 'filterSearchFiltersGeneration']
        );

        // Events for the admin board.
        $controllers = [
            'Omeka\Controller\Admin\Item',
            // 'Omeka\Controller\Admin\ItemSet',
            // 'Omeka\Controller\Admin\Media',
        ];
        foreach ($controllers as $controller) {
            $sharedEventManager->attach(
                $controller,
                'view.show.section_nav',
                [$this, 'addTab']
            );
            $sharedEventManager->attach(
                $controller,
                'view.show.after',
                [$this, 'displayListAndForm']
            );

            // Add the details to the resource browse admin pages.
            $sharedEventManager->attach(
                $controller,
                'view.details',
                [$this, 'viewDetails']
            );

            // Add the tab form to the resource edit admin pages.
            // Note: it can't be added to the add form, because it has no sense
            // to generateur something that does not exist.
            $sharedEventManager->attach(
                $controller,
                'view.edit.section_nav',
                [$this, 'addTab']
            );
            $sharedEventManager->attach(
                $controller,
                'view.edit.form.after',
                [$this, 'displayList']
            );
        }

        // Display a warn before uninstalling.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
        );

        // Module Csv Import.
        $sharedEventManager->attach(
            \CSVImport\Form\MappingForm::class,
            'form.add_elements',
            [$this, 'addCsvImportFormElements']
        );
    }

    /**
     * Add the generation data to the resource JSON-LD.
     *
     * @param Event $event
     */
    public function filterJsonLd(Event $event): void
    {
        if (!$this->userCanRead()) {
            return;
        }

        $resource = $event->getTarget();
        $entityColumnName = $this->columnNameOfRepresentation($resource);
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $generations = $api
            ->search('generations', [$entityColumnName => $resource->id()], ['responseContent' => 'reference'])
            ->getContent();
        if ($generations) {
            $jsonLd = $event->getParam('jsonLd');
            $jsonLd['o:generation'] = $generations;
            $event->setParam('jsonLd', $jsonLd);
        }
    }

    /**
     * Display the advanced search form for generations via partial.
     *
     * @param Event $event
     */
    public function displayAdvancedSearchGeneration(Event $event): void
    {
        $query = $event->getParam('query', []);
        $query['datetime'] = isset($query['datetime']) ? $query['datetime'] : '';
        $partials = $event->getParam('partials', []);

        $partials[] = 'common/advanced-search/date-time-generation';

        // TODO Add a search form on the metadata of the resources.

        $event->setParam('query', $query);
        $event->setParam('partials', $partials);
    }

    /**
     * Filter search filters of generations for display.
     *
     * @param Event $event
     */
    public function filterSearchFiltersGeneration(Event $event): void
    {
        $query = $event->getParam('query', []);
        $view = $event->getTarget();
        $normalizeDateTimeQuery = $view->plugin('normalizeDateTimeQuery');
        if (empty($query['datetime'])) {
            $query['datetime'] = [];
        } else {
            if (!is_array($query['datetime'])) {
                $query['datetime'] = [$query['datetime']];
            }
            foreach ($query['datetime'] as $key => $datetime) {
                $datetime = $normalizeDateTimeQuery($datetime);
                if ($datetime) {
                    $query['datetime'][$key] = $datetime;
                } else {
                    unset($query['datetime'][$key]);
                }
            }
        }
        if (!empty($query['created'])) {
            $datetime = $normalizeDateTimeQuery($query['created'], 'created');
            if ($datetime) {
                $query['datetime'][] = $datetime;
            }
        }
        if (!empty($query['modified'])) {
            $datetime = $normalizeDateTimeQuery($query['modified'], 'modified');
            if ($datetime) {
                $query['datetime'][] = $datetime;
            }
        }

        if (empty($query['datetime'])) {
            return;
        }

        $filters = $event->getParam('filters');
        $translate = $view->plugin('translate');
        $queryTypes = [
            '>' => $translate('after'),
            '>=' => $translate('after or on'),
            '=' => $translate('on'),
            '<>' => $translate('not on'),
            '<=' => $translate('before or on'),
            '<' => $translate('before'),
            'gte' => $translate('after or on'),
            'gt' => $translate('after'),
            'eq' => $translate('on'),
            'neq' => $translate('not on'),
            'lte' => $translate('before or on'),
            'lt' => $translate('before'),
            'ex' => $translate('has any date / time'),
            'nex' => $translate('has no date / time'),
        ];

        $next = false;
        foreach ($query['datetime'] as $queryRow) {
            $joiner = $queryRow['joiner'];
            $field = $queryRow['field'];
            $type = $queryRow['type'];
            $datetimeValue = $queryRow['value'];

            $fieldLabel = $field === 'modified' ? $translate('Modified') : $translate('Created');
            $filterLabel = $fieldLabel . ' ' . $queryTypes[$type];
            if ($next) {
                if ($joiner === 'or') {
                    $filterLabel = $translate('OR') . ' ' . $filterLabel;
                } else {
                    $filterLabel = $translate('AND') . ' ' . $filterLabel;
                }
            } else {
                $next = true;
            }
            $filters[$filterLabel][] = $datetimeValue;
        }

        $event->setParam('filters', $filters);
    }

    public function addCsvImportFormElements(Event $event): void
    {
        /** @var \CSVImport\Form\MappingForm $form */
        $form = $event->getTarget();
        $resourceType = $form->getOption('resource_type');
        if ($resourceType !== 'generations') {
            return;
        }

        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        if (!$acl->userIsAllowed(Generation::class, 'create')) {
            return;
        }

        $form->addResourceElements();
        if ($acl->userIsAllowed(\Generateur\Entity\Generation::class, 'change-owner')) {
            $form->addOwnerElement();
        }
        $form->addProcessElements();
        $form->addAdvancedElements();
    }

    /**
     * Add the headers for admin management.
     *
     * @param Event $event
     */
    public function addHeadersAdmin(Event $event): void
    {
        // Hacked, because the admin layout doesn't use a partial or a trigger
        // for the search engine.
        $view = $event->getTarget();
        // TODO How to attach all admin events only before 1.3?
        if (!$view->params()->fromRoute('__ADMIN__')) {
            return;
        }
        $view->headLink()
            ->appendStylesheet($view->assetUrl('css/generateur-admin.css', 'Generateur'));
        $searchUrl = sprintf('var searchGenerationsUrl = %s;', json_encode($view->url('admin/generation/default', ['action' => 'browse'], true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $view->headScript()
            ->appendScript($searchUrl)
            ->appendFile($view->assetUrl('js/generateur-admin.js', 'Generateur'), 'text/javascript', ['defer' => 'defer']);
    }

    /**
     * Add a tab to section navigation.
     *
     * @param Event $event
     */
    public function addTab(Event $event): void
    {
        $sectionNav = $event->getParam('section_nav');
        $sectionNav['generateur'] = 'Generations'; // @translate
        $event->setParam('section_nav', $sectionNav);
    }

    /**
     * Display a partial for a resource.
     *
     * @param Event $event
     */
    public function displayListAndForm(Event $event): void
    {
        $resource = $event->getTarget()->resource;
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $allowed = $acl->userIsAllowed(\Omeka\Entity\Item::class, 'create');

        echo '<div id="generateur" class="section generateur">';
        $this->displayResourceGenerations($event, $resource, false);
        if ($allowed) {
            $this->displayForm($event);
        }
        echo '</div>';
    }

    /**
     * Display the list for a resource.
     *
     * @param Event $event
     */
    public function displayList(Event $event): void
    {
        echo '<div id="generateur" class="section generateur">';
        $vars = $event->getTarget()->vars();
        // Manage add/edit form.
        if (isset($vars->resource)) {
            $resource = $vars->resource;
        } elseif (isset($vars->item)) {
            $resource = $vars->item;
        } elseif (isset($vars->itemSet)) {
            $resource = $vars->itemSet;
        } elseif (isset($vars->media)) {
            $resource = $vars->media;
        } else {
            $resource = null;
        }
        $vars->offsetSet('resource', $resource);
        $this->displayResourceGenerations($event, $resource, false);
        echo '</div>';
    }

    /**
     * Display the details for a resource.
     *
     * @param Event $event
     */
    public function viewDetails(Event $event): void
    {
        $representation = $event->getParam('entity');
        // TODO Use a paginator to limit and display all generations dynamically in the details view (using api).
        $this->displayResourceGenerations($event, $representation, true, ['limit' => 10]);
    }

    /**
     * Display a form.
     *
     * @param Event $event
     */
    public function displayForm(Event $event): void
    {
        $view = $event->getTarget();
        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
        $resource = $event->getTarget()->resource;

        $services = $this->getServiceLocator();
        $viewHelpers = $services->get('ViewHelperManager');
        $url = $viewHelpers->get('url');

        $options = [
            'resource' => $resource,
        ];
        $attributes = [];
        $attributes['action'] = $url(
            'admin/generation/default',
            ['action' => 'generate'],
            ['query' => ['redirect' => $resource->adminUrl() . '#generateur']]
        );

        // TODO Get the post when an error occurs (but this is never the case).
        // Currently, this is a redirect.
        // $request = $services->get('Request');
        // $isPost = $request->isPost();
        // if ($isPost) {
        //     $controllerPlugins = $services->get('ControllerPluginManager');
        //     $params = $controllerPlugins->get('params');
        //     $data = $params()->fromPost();
        // }
        $data = [];

        echo $view->showGenerateurForm($resource, $options, $attributes, $data);
    }

    /**
     * Helper to display a partial for a resource.
     *
     * @param Event $event
     * @param AbstractResourceEntityRepresentation $resource
     * @param bool $listAsDiv Return the list with div, not ul.
     */
    protected function displayResourceGenerations(
        Event $event,
        AbstractResourceEntityRepresentation $resource,
        $listAsDiv = false,
        array $query = []
    ): void {
        $services = $this->getServiceLocator();
        $controllerPlugins = $services->get('ControllerPluginManager');
        $resourceGenerationsPlugin = $controllerPlugins->get('resourceGenerations');
        $generations = $resourceGenerationsPlugin($resource, $query);
        $totalResourceGenerationsPlugin = $controllerPlugins->get('totalResourceGenerations');
        $totalGenerations = $totalResourceGenerationsPlugin($resource, $query);
        $partial = $listAsDiv
            // Quick detail view.
            ? 'common/admin/generation-resource'
            // Full view in tab.
            : 'common/admin/generation-resource-list';
        echo $event->getTarget()->partial(
            $partial,
            [
                'resource' => $resource,
                'generations' => $generations,
                'totalGenerations' => $totalGenerations,
            ]
        );
    }

    /**
     * Check if a user can read generations.
     *
     * @todo Is it really useful to check if user can read generations?
     *
     * @return bool
     */
    protected function userCanRead()
    {
        $userIsAllowed = $this->getServiceLocator()->get('ViewHelperManager')
            ->get('userIsAllowed');
        return $userIsAllowed(Generation::class, 'read');
    }

    /**
     * Helper to get the column id of a representation.
     *
     * Note: Resource representation have method resourceName(), but site page
     * and user don't. Site page has no getControllerName().
     *
     * @param AbstractEntityRepresentation $representation
     * @return string
     */
    protected function columnNameOfRepresentation(AbstractEntityRepresentation $representation)
    {
        $entityColumnNames = [
            'item-set' => 'resource_id',
            'item' => 'resource_id',
            'media' => 'resource_id',
            'user' => 'owner_id',
        ];
        return $entityColumnNames[$representation->getControllerName()];
    }
}

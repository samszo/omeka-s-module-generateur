<?php
namespace Generateur;

return [
    'entity_manager' => [
        'resource_discriminator_map' => [
            // The three entities are sub-classes of the abstract class Entity\GenerationPart,
            // that is a subclass of Resource.
            // This solution allows to search the property values in the three parts simpler.
            // The other solution is to make the main generation a sub-part too, and to do
            // the search inside the abstract part, and to use an adapter for it (like Resource).
            // It is cleaner, even it may be more complex because bodies and targets
            // depends on the main generation part. So if needed later. Note that the ids
            // should be stable.

            Entity\Generation::class => Entity\Generation::class,
            // oa:hasBody can be used by oa:Generation only.
            Entity\GenerationBody::class => Entity\GenerationBody::class,
            // oa:hasTarget can be used by oa:Generation only.
            Entity\GenerationTarget::class => Entity\GenerationTarget::class,
            // May be added for full coverage of data model (useless for current modules):
            // oa:hasSelector can be used by body (rare) or target (mainly for
            // cartographic generation here). The selector is not a Resource,
            // but depends on oa:ResourceSelection.
            // oa:refinedBy can be used by oa:hasSelector and oa:hasState only.
            // The oa:refinedBy is another selector or state.
            // oa:hasSource (for body (rare) or target).
            // as:items
            // oa:hasState
            // oa:hasStartSelector
            // oa:hasEndSelector
            // oa:renderedVia
            // oa:styledBy
            // as:generator
            // dcterms:creator
            // schema:audience
            // @link https://www.w3.org/TR/generation-vocab/#as-application
            // TODO Any property can be another resource (uri), so it may be genericized, but the structure of
            // Omeka is not designed in such a way (and all values must be in the table value). Use datatype to bypass? So oa:resource:item?
            // The current desing simplifies search queries too.
        ],
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'generations' => Api\Adapter\GenerationAdapter::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'normalizeDateTimeQuery' => View\Helper\NormalizeDateTimeQuery::class,
        ],
        'factories' => [
            'showGenerateurForm' => Service\ViewHelper\ShowGenerateurFormFactory::class,
            'generations' => Service\ViewHelper\GenerationsFactory::class,
            // For compatibility with Omeka < 1.2.1.
            'resourceTemplateSelect' => Service\ViewHelper\ResourceTemplateSelectFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
        'factories' => [
            Form\GenerateurForm::class => Service\Form\GenerateurFormFactory::class,
            Form\QuickSearchForm::class => Service\Form\QuickSearchFormFactory::class,
            Form\ResourceForm::class => Service\Form\ResourceFormFactory::class,
            // For compatibility with Omeka < 1.2.1.
            Form\Element\ResourceTemplateSelect::class => Service\Form\Element\ResourceTemplateSelectFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Admin\GenerationController::class => Controller\Admin\GenerationController::class,
            Controller\Site\GenerationController::class => Controller\Site\GenerationController::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'isAnnotable' => Mvc\Controller\Plugin\IsAnnotable::class,
            'resourceGenerations' => Mvc\Controller\Plugin\ResourceGenerations::class,
            'totalResourceGenerations' => Mvc\Controller\Plugin\TotalResourceGenerations::class,
        ],
        'factories' => [
            'generationPartMapper' => Service\ControllerPlugin\GenerationPartMapperFactory::class,
            'divideMergedValues' => Service\ControllerPlugin\DivideMergedValuesFactory::class,
            'resourceTemplateGenerationPartMap' => Service\ControllerPlugin\ResourceTemplateGenerationPartMapFactory::class,
        ],
    ],
    'navigation' => [
        'AdminResource' => [
            'generateur' => [
                'label' => 'Generations', // @translate
                'class' => 'generations far fa-hand-o-up',
                'route' => 'admin/generateur/default',
                'resource' => Controller\Admin\GenerationController::class,
                'privilege' => 'browse',
                'pages' => [
                    [
                        'route' => 'admin/generateur/id',
                        'controller' => Controller\Admin\GenerationController::class,
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/generateur/default',
                        'controller' => Controller\Admin\GenerationController::class,
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'generateur' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/generation',
                            'defaults' => [
                                '__NAMESPACE__' => 'Generateur\Controller\Site',
                                '__SITE__' => true,
                                'controller' => Controller\Site\GenerationController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'admin' => [
                'child_routes' => [
                    'generateur' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/generation',
                            'defaults' => [
                                '__NAMESPACE__' => 'Generateur\Controller\Admin',
                                '__ADMIN__' => true,
                                'controller' => Controller\Admin\GenerationController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'js_translate_strings' => [
        'Search generations', // @target
        'Generations', // @target
        'Web Open Generation', // @target
        'With the class <code>oa:Generation</code>, it’s important to choose the part of the generation to which the property is attached:', // @target
        'It can be the generation itself (default), but the body or the target too.', // @target
        'For example, to add an indication on a uncertainty of  a highlighted segment, the property should be attached to the target, but the description of a link should be attached to the body.', // @target
        'Standard non-ambivalent properties are automatically managed.', // @target
        'Generation', // @target
        'Generation part', // @target
        'To comply with Generation data model, select the part of the generation this property will belong to.', // @target
        'This option cannot be imported/exported currently.', // @target
        'Generation', // @target
        'Generation body', // @target
        'Generation target', // @target
    ],
    'generateur' => [
        'config' => [
            'generateur_public_allow_view' => true,
            'generateur_public_allow_generateur' => false,
            'generateur_resource_template_data' => [],
        ],
        'site_settings' => [
            'generateur_append_item_set_show' => false,
            'generateur_append_item_show' => true,
            'generateur_append_media_show' => false,
        ],
    ],
    'csvimport' => [
        'mappings' => [
            'generations' => [
                'label' => 'Generations', // @translate
                'mappings' => [
                    Mapping\GenerationMapping::class,
                    \CSVImport\Mapping\PropertyMapping::class,
                ],
            ],
        ],
        'user_settings' => [
            'csvimport_automap_user_list' => [
                'motivation' => 'generation {oa:motivatedBy}',
                'purpose' => 'generation_target {oa:hasPurpose}',
            ],
        ],
    ],
];

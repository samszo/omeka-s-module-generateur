<?php
namespace Generateur;

return [
    'entity_manager' => [
        'resource_discriminator_map' => [
            Entity\Generation::class => Entity\Generation::class,
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
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\GenerateurForm::class => Service\Form\GenerateurFormFactory::class,
            Form\QuickSearchForm::class => Service\Form\QuickSearchFormFactory::class,
            Form\ResourceForm::class => Service\Form\ResourceFormFactory::class,
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
            'isGenerable' => Mvc\Controller\Plugin\IsGenerable::class,
            'resourceGenerations' => Mvc\Controller\Plugin\ResourceGenerations::class,
            'totalResourceGenerations' => Mvc\Controller\Plugin\TotalResourceGenerations::class,
        ],
    ],
    'navigation' => [
        'AdminResource' => [
            'generation' => [
                'label' => 'Generations', // @translate
                'class' => 'generations fas fa-recycle',
                'route' => 'admin/generation/default',
                'resource' => Controller\Admin\GenerationController::class,
                'privilege' => 'browse',
                'pages' => [
                    [
                        'route' => 'admin/generation/id',
                        'controller' => Controller\Admin\GenerationController::class,
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/generation/default',
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
                    'generation' => [
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
                    'generation' => [
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
        'Search generations', // @translate
        'Generations', // @translate
        'Generation', // @translate
    ],
    'blocksdisposition' => [
        'views' => [
            'item_set_show' => [
                // 'Generation',
            ],
            'item_show' => [
                'Generation',
            ],
            'media_show' => [
                // 'Generation',
            ],
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
    ],
    'generateur' => [
        'config' => [
            'generateur_public_allow_view' => true,
            'generateur_public_allow_generate' => false,
        ],
    ],
];

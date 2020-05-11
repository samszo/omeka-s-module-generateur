<?php
namespace Generateur\Form;

use Generateur\Form\Element\ResourceTemplateSelect;
use Omeka\View\Helper\Api;
use Zend\Form\Element;

class ResourceForm extends \Omeka\Form\ResourceForm
{
    /**
     * @var Api
     */
    protected $api;

    public function init()
    {
        parent::init();

        $api = $this->api;

        // A resource template with class "oa:Generation" is required when manually edited.
        $this->add([
            'name' => 'o:resource_template[o:id]',
            'type' => ResourceTemplateSelect::class,
            'options' => [
                'label' => 'Template', // @translate
                'empty_option' => null,
                'query' => [
                    'resource_class' => 'oa:Generation',
                ],
            ],
            'attributes' => [
                'class' => 'chosen-select',
            ],
        ]);

        // The default resource template of an generation is Generation.
        $resourceTemplateId = $api->searchOne('resource_templates', ['label' => 'Generation'])->getContent()->id();
        $this->get('o:resource_template[o:id]')
            ->setValue($resourceTemplateId);

        // The resource class of an generation is always oa:Generation.
        $resourceClass = $api->searchOne('resource_classes', ['term' => 'oa:Generation'])->getContent();
        $this->add([
            'name' => 'o:resource_class[o:id]',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Class', // @translate
                'value_options' => [
                    'oa' => [
                        'label' => $resourceClass->vocabulary()->label(),
                        'options' => [
                            [
                                'label' => $resourceClass->label(),
                                'value' => $resourceClass->id(),
                                'attributes' => [
                                    'data-term' => 'oa:Generation',
                                    'data-resource-class-id' => $resourceClass->id(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'attributes' => [
                'value' => $resourceClass->id(),
                'class' => 'chosen-select',
            ],
        ]);
    }

    /**
     * @param Api $api
     */
    public function setApi(Api $api)
    {
        $this->api = $api;
    }
}

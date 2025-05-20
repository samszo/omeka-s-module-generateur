<?php
namespace Generateur\Form;

use Omeka\View\Helper\Api;

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

        // The default resource template of a generation is Generation and can only be a génération.
        $resourceTemplateId = $api->searchOne('resource_templates', ['label' => 'Génération'])->getContent()->id();

        $resourceTemplateElement = $this->get('o:resource_template[o:id]');
        $resourceValueOptions = $resourceTemplateElement->getOption('resource_value_options');
        $resourceValueOptions['query'] = ['label' => 'Génération'];
        $resourceTemplateElement
            ->setOption('resource_value_options', $resourceValueOptions)
            ->setValue($resourceTemplateId);
    }

    /**
     * @param Api $api
     */
    public function setApi(Api $api)
    {
        $this->api = $api;
    }
}

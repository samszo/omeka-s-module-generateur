<?php
namespace Generateur\Form;

use Omeka\View\Helper\Api;
use Laminas\Form\Element;
use Laminas\Form\Form;

class GenerateurForm extends Form
{
    /**
     * @var Api
     */
    protected $api;

    public function init()
    {
        // TODO Move all static params into generateur controller?
        // TODO Convert with fieldsets to allow check via getData().

        $resource = $this->getOption('resource');
        $resourceTemplate = $this->api->searchOne('resource_templates', ['label' => 'Génération'])->getContent();

        $this
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'o:resource[o:id]',
                'attributes' => ['value' => $resource ? $resource->id() : ''],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'o:resource_template[o:id]',
                'attributes' => ['value' => $resourceTemplate ? $resourceTemplate->id() : ''],
            ])

            ->add([
                'name' => 'o:is_public',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Is public', // @translate
                ],
                'attributes' => [
                    'value' => 1,
                ],
            ])

            ->add([
                'name' => 'bCache',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Use cache', // @translate
                ],
                'attributes' => [
                    'value' => 1,
                ],
            ])


            ->add([
                'type' => Element\Submit::class,
                'name' => 'submit',
                'attributes' => [
                    'value' => 'Generate !', // @translate
                    'class' => 'generations fas fa-recycle',
                ],
            ])

            ->add([
                'type' => Element\Submit::class,
                'name' => 'submitStructure',
                'attributes' => [
                    'value' => 'Structure !', // @translate
                    'class' => 'generations fas fa-wrench',
                ],
            ])

        ;
    }

    /**
     * @param Api $api
     */
    public function setApi(Api $api)
    {
        $this->api = $api;
    }
}

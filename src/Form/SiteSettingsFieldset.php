<?php
namespace Generateur\Form;

use Zend\Form\Element;
use Zend\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    public function init()
    {
        $this->setLabel('Generateur'); // @translate

        $this->add([
            'name' => 'generateur_append_item_set_show',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Append generations automatically to item set page', // @translate
                'info' => 'If unchecked, the generations can be added via the helper in the theme or the block in any page.', // @translate
            ],
            'attributes' => [
                'id' => 'generateur_append_item_set_show',
            ],
        ]);

        $this->add([
            'name' => 'generateur_append_item_show',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Append generations automatically to item page', // @translate
                'info' => 'If unchecked, the generations can be added via the helper in the theme or the block in any page.', // @translate
            ],
            'attributes' => [
                'id' => 'generateur_append_item_show',
            ],
        ]);

        $this->add([
            'name' => 'generateur_append_media_show',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Append generations automatically to media page', // @translate
                'info' => 'If unchecked, the generations can be added via the helper in the theme or the block in any page.', // @translate
            ],
            'attributes' => [
                'id' => 'generateur_append_media_show',
            ],
        ]);
    }
}

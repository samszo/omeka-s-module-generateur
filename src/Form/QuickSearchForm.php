<?php

namespace Generateur\Form;

use Zend\EventManager\Event;
use Zend\EventManager\EventManagerAwareTrait;
use Zend\Form\Element;
use Zend\Form\Form;
use Zend\View\Helper\Url;

class QuickSearchForm extends Form
{
    use EventManagerAwareTrait;

    /**
     * @var Url
     */
    protected $urlHelper;

    public function init()
    {
        $this->setAttribute('method', 'get');

        $this->add([
            'type' => Element\Text::class,
            'name' => 'created',
            'options' => [
                'label' => 'Date generated', // @translate
            ],
            'attributes' => [
                'placeholder' => 'Set a date with optional comparator…', // @translate
            ],
        ]);

        $addEvent = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($addEvent);

        $this->add([
            'name' => 'submit',
            'type' => Element\Submit::class,
            'attributes' => [
                'value' => 'Search', // @translate
                'type' => 'submit',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $event = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($event);
    }

    /**
     * @param Url $urlHelper
     */
    public function setUrlHelper(Url $urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return \Zend\View\Helper\Url
     */
    public function getUrlHelper()
    {
        return $this->urlHelper;
    }
}

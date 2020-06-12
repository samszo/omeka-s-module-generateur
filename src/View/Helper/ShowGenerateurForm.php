<?php
namespace Generateur\View\Helper;

use Generateur\Form\GenerateurForm;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\Item;
use Zend\View\Helper\AbstractHelper;

class ShowGenerateurForm extends AbstractHelper
{
    protected $formElementManager;

    public function __construct($formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }

    /**
     * Return the partial to display the generateur form.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $options
     * @param array $attributes
     * @param array $data
     * @return string
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = [], array $attributes = [], array $data = [])
    {
        $view = $this->getView();
        if (!$view->userIsAllowed(Item::class, 'create')) {
            return '';
        }

        /** @var \Generateur\Form\GenerateurForm $form */
        $form = $this->formElementManager->get(GenerateurForm::class);
        $form->setOptions($options);
        $form->init();
        $form->setData($data);
        $form->setAttributes($attributes);
        $view->vars()->offsetSet('generateurForm', $form);
        return $view->partial('common/generateur-form');
    }
}

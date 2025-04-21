<?php
namespace Generateur\Service\Form;

use Interop\Container\ContainerInterface;
use Generateur\Form\QuickSearchForm;
use Laminas\ServiceManager\Factory\FactoryInterface;

class QuickSearchFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new QuickSearchForm(null, !$options ? [] : $options);
        $form->setEventManager($services->get('EventManager'));
        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $form->setUrlHelper($urlHelper);
        return $form;
    }
}

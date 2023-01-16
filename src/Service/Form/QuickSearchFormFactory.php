<?php declare(strict_types=1);
namespace Generateur\Service\Form;

use Generateur\Form\QuickSearchForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class QuickSearchFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new QuickSearchForm(null, $options);
        $form->setEventManager($services->get('EventManager'));
        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $form->setUrlHelper($urlHelper);
        return $form;
    }
}

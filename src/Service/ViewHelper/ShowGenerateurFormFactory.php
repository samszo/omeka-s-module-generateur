<?php
namespace Generateur\Service\ViewHelper;

use Generateur\View\Helper\ShowGenerateurForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ShowGenerateurFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $formElementManager = $services->get('FormElementManager');
        return new ShowGenerateurForm($formElementManager);
    }
}

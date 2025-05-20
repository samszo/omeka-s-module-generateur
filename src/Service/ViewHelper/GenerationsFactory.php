<?php
namespace Generateur\Service\ViewHelper;

use Generateur\View\Helper\Generations;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class GenerationsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $controllerPlugins = $services->get('ControllerPluginManager');
        $resourceGenerationsPlugin = $controllerPlugins->get('resourceGenerations');
        return new Generations($resourceGenerationsPlugin);
    }
}

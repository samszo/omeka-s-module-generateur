<?php
namespace Generateur\Service\ViewHelper;

use Generateur\View\Helper\Moteur;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Generateur\View\Helper\MoteurViewHelper;

class MoteurFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');
        $sql = $services->get('ViewHelperManager')->get('GenerateurSql');
        return new MoteurViewHelper($api,$logger,$sql);
    }
}

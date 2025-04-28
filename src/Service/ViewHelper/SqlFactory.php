<?php
namespace Generateur\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Generateur\View\Helper\GenerateurSql;

class SqlFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $cnx = $services->get('Omeka\Connection');
        $logger = $services->get('Omeka\Logger');

        return new GenerateurSql($api, $cnx, $logger);
    }
}
<?php declare(strict_types=1);
namespace Generateur\Service\ViewHelper;

use Generateur\View\Helper\MoteurViewHelper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MoteurFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        return new MoteurViewHelper($api);
    }
}

<?php declare(strict_types=1);
namespace Generateur\Mvc\Controller\Plugin;

use Generateur\Api\Representation\GenerationRepresentation;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class ResourceGenerations extends AbstractPlugin
{
    /**
     * Helper to return the list of generations of a resource.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $query
     * @return GenerationRepresentation[]
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $query = [])
    {
        $query['resource_id'] = $resource->id();
        return $this->getController()->api()
            ->search('generations', $query)
            ->getContent();
    }
}

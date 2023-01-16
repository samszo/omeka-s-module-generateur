<?php
namespace Generateur\Mvc\Controller\Plugin;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class IsGenerable extends AbstractPlugin
{
    protected $generables = [
        \Omeka\Api\Representation\ItemRepresentation::class,
        // \Omeka\Api\Representation\MediaRepresentation::class,
        // \Omeka\Api\Representation\ItemSetRepresentation::class,
    ];

    /**
     * Check if a resource is generable.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return bool
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        return in_array(get_class($resource), $this->generables);
    }
}

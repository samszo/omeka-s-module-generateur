<?php
namespace Generateur\View\Helper;

use Generateur\Mvc\Controller\Plugin\ResourceGenerations;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Laminas\View\Helper\AbstractHelper;

class Generations extends AbstractHelper
{
    /**
     * @var ResourceGenerations
     */
    protected $resourceGenerationsPlugin;

    public function __construct(ResourceGenerations $resourceGenerationsPlugin)
    {
        $this->resourceGenerationsPlugin = $resourceGenerationsPlugin;
    }

    /**
     * Return the partial to display generations.
     *
     * @return string
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        $resourceGenerationsPlugin = $this->resourceGenerationsPlugin;
        $generations = $resourceGenerationsPlugin($resource);
        echo $this->getView()->partial(
            'common/site/generation-resource',
            [
                'resource' => $resource,
                'generations' => $generations,
            ]
        );
    }
}

<?php
namespace Generateur\Api\Representation;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class GenerationRepresentation extends AbstractResourceEntityRepresentation
{
    /**
     * @var \Generateur\Entity\Generation
     */
    protected $resource;

    public function getControllerName()
    {
        return 'generation';
    }

    public function getResourceJsonLdType()
    {
        return 'o-module-generateur:Generation';
    }

    public function getResourceJsonLd()
    {
        return [
            'o:resource' => $this->resource()->getReference(),
        ];
    }

    /**
     * Return the initial resource for this generation.
     *
     * @return \Omeka\Api\Representation\AbstractResourceEntityRepresentation
     */
    public function resource()
    {
        return $this->getAdapter('resources')
            ->getRepresentation($this->resource->getResource());
    }

    /**
     * Return the first media of the resource.
     *
     * {@inheritDoc}
     */
    public function primaryMedia()
    {
        $baseResource = $this->resource();
        if (method_exists($baseResource, 'primaryMedia')) {
            return $baseResource->primaryMedia();
        }
        return null;
    }

    public function siteUrl($siteSlug = null, $canonical = false)
    {
        if (!$siteSlug) {
            $siteSlug = $this->getServiceLocator()->get('Application')
                ->getMvcEvent()->getRouteMatch()->getParam('site-slug');
        }
        $url = $this->getViewHelper('Url');
        return $url(
            'site/resource-id',
            [
                'site-slug' => $siteSlug,
                'controller' => 'generation',
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }

    public function displayTitle($default = null)
    {
        $title = $this->title();
        if (null !== $title) {
            return $title;
        }

        if ($default === null) {
            $translator = $this->getServiceLocator()->get('MvcTranslator');
            $default = sprintf($translator->translate('[Generation #%d]'), $this->id());
        }

        return $default;
    }
}

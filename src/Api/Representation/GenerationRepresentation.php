<?php
namespace Generateur\Api\Representation;

use Generateur\Api\Adapter\GenerationBodyHydrator;
use Generateur\Api\Adapter\GenerationTargetHydrator;
use Generateur\Entity\Generation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class GenerationRepresentation extends AbstractResourceEntityRepresentation
{
    /**
     * @var Generation
     */
    protected $resource;

    public function getControllerName()
    {
        return 'generation';
    }

    public function getResourceJsonLdType()
    {
        return 'oa:Generation';
    }

    /**
     * {@inheritDoc}
     *
     * Unlike integrated resources, the class "oa:Generation" is predefined and
     * cannot be changed or merged.
     *
     * @link https://www.w3.org/TR/generation-vocab/#generation
     * @see \Omeka\Api\Representation\AbstractResourceEntityRepresentation::getJsonLdType()
     */
    public function getJsonLdType()
    {
        return $this->getResourceJsonLdType();
    }

    public function getResourceJsonLd()
    {
        $result = [];
        $bodies = $this->bodies();
        // Complies with https://www.w3.org/TR/generation-model/#cardinality-of-bodies-and-targets
        if ($bodies) {
            $result['oa:hasBody'] = $bodies;
        }
        $result['oa:hasTarget'] = $this->targets();
        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * Two rdf contexts are used: Open Generation (main) and Omeka (secondary).
     * @todo Extend oa model: oa:styledBy should be a SVG stylesheet, but oa:SvgStylesheed does not exist (only CssStylesheet). But GeoJson allows to manage css.
     *
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::jsonSerialize()
     */
    public function jsonSerialize()
    {
        $jsonLd = parent::jsonSerialize();

        $jsonLd['@context'] = [
            'oa' => 'http://www.w3.org/ns/anno.jsonld',
            'o' => 'http://localhost/OmekaS/api-context',
        ];

        return $jsonLd;
    }

    /**
     * Get the bodies assigned to this generation.
     *
     * @todo Remove bodies without properties.
     *
     * @return GenerationBodyRepresentation[]
     */
    public function bodies()
    {
        $bodies = [];
        $bodyAdapter = new GenerationBodyHydrator();
        $bodyAdapter->setServiceLocator($this->getServiceLocator());
        foreach ($this->resource->getBodies() as $bodyEntity) {
            $bodies[] = $bodyAdapter->getRepresentation($bodyEntity);
        }
        return $bodies;
    }

    /**
     * Return the first body if one exists.
     *
     * @return GenerationBodyRepresentation
     */
    public function primaryBody()
    {
        $bodies = $this->bodies();
        return $bodies ? reset($bodies) : null;
    }

    /**
     * Get the targets assigned to this generation.
     *
     * @return GenerationTargetRepresentation[]
     */
    public function targets()
    {
        $targets = [];
        $targetAdapter = new GenerationTargetHydrator();
        $targetAdapter->setServiceLocator($this->getServiceLocator());
        foreach ($this->resource->getTargets() as $targetEntity) {
            $targets[] = $targetAdapter->getRepresentation($targetEntity);
        }
        return $targets;
    }

    /**
     * Return the first target if one exists.
     *
     * @return GenerationTargetRepresentation
     */
    public function primaryTarget()
    {
        $targets = $this->targets();
        return $targets ? reset($targets) : null;
    }

    /**
     * Return the target resources if any.
     *
     * @return AbstractResourceEntityRepresentation[]
     */
    public function targetSources()
    {
        $result = [];
        $targets = $this->targets();
        foreach ($targets as $target) {
            $result = array_merge($result, array_values($target->sources()));
        }
        return array_values($result);
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
        $title = $this->value('dcterms:title', [
            'default' => null,
        ]);

        if ($title !== null) {
            return (string) $title;
        }

        if ($default === null) {
            $translator = $this->getServiceLocator()->get('MvcTranslator');
            $default = sprintf($translator->translate('[Generation #%d]'), $this->id());
        }

        return $default;
    }

    /**
     * Get the annotator of this generation.
     *
     * @param string $default
     * @return \Omeka\Api\Representation\UserRepresentation|array
     */
    public function annotator($default = null)
    {
        $owner = $this->owner();
        if ($owner) {
            return $owner;
        }

        // TODO The annotator may be a public or a deleted owner.
        $public = [];
        $creator = $this->value('dcterms:creator');
        if ($creator) {
            $public['id'] = true;
            $public['name'] = (string) $creator;
        } else {
            $public['id'] = false;
            if (is_null($default)) {
                $translator = $this->getServiceLocator()->get('MvcTranslator');
                $public['name'] = $translator->translate('[Unknown]'); // @translate
            } else {
                $public['name'] = $default;
            }
        }

        $public['email'] = (string) $this->value('foaf:mbox');

        return $public;
    }

    /**
     * Get the link to all generations of the annotator of this generation.
     *
     * @param string $default
     * @return string
     */
    public function linkAnnotator($default = null)
    {
        $services = $this->getServiceLocator();

        $annotator = $this->annotator();
        $query = [];
        if (is_object($annotator)) {
            $text = $annotator->name();
            $query['owner_id'] = $annotator->id();
        } else {
            // TODO Manage anonymous user deleted user.
            $text = $annotator['name'];
            $query['annotator'] = $annotator['id'] ? $text : '0';
        }

        $status = $services->get('Omeka\Status');
        $url = $this->getViewHelper('Url');
        // Make compatible with Omeka < 1.2.1.
        if (method_exists($status, 'isAdminRequest')) {
            if ($status->isSiteRequest()) {
                $url = $url('site/generateur/default', [], ['query' => $query], true);
            } elseif ($status->isAdminRequest()) {
                $url = $url('admin/generateur/default', [], ['query' => $query]);
            } else {
                return;
            }
        } else {
            $routeMatch = $services->get('Application')->getMvcEvent()->getRouteMatch();
            if ($routeMatch->getParam('__SITE__')) {
                $url = $url('site/generateur/default', [], ['query' => $query], true);
            } elseif ($routeMatch->getParam('__ADMIN__')) {
                $url = $url('admin/generateur/default', [], ['query' => $query]);
            } else {
                return;
            }
        }

        $hyperlink = $this->getViewHelper('hyperlink');
        $escapeHtml = $this->getViewHelper('escapeHtml');
        return $hyperlink->raw($escapeHtml($text), $url);
    }

    /**
     * Merge values of the generation, bodies and the targets.
     *
     *  Most of the time, there is only one body and one target, and each entity
     *  has its specific properties according to the specification. So the merge
     *  create a simpler list of values.
     *
     * @uses AbstractResourceEntityRepresentation::values()
     *
     * @deprecated Will be replaced by a new resource form, on the resource template base.
s     *
     * @return array
     */
    public function mergedValues()
    {
        $values = $this->values();
        // Note: array_merge_recursive may failed for memory overkill.
        foreach ($this->bodies() as $body) {
            // $values = array_merge_recursive($values, $body->values());
            foreach ($body->values() as $term => $termValues) {
                if (isset($values[$term]['property'])) {
                    $values[$term]['values'] = empty($values[$term]['values'])
                        ? $termValues['values']
                        : array_merge($values[$term]['values'], $termValues['values']);
                } else {
                    $values[$term] = $termValues;
                }
            }
        }
        foreach ($this->targets() as $target) {
            // $values = array_merge_recursive($values, $target->values());
            foreach ($target->values() as $term => $termValues) {
                if (isset($values[$term]['property'])) {
                    $values[$term]['values'] = empty($values[$term]['values'])
                        ? $termValues['values']
                        : array_merge($values[$term]['values'], $termValues['values']);
                } else {
                    $values[$term] = $termValues;
                }
            }
        }
        return $values;
    }

    /**
     * Separate properties between generation, bodies and targets.
     *
     * Note: only standard generation data are managed. Specific properties are
     * kept in the generation.
     *
     * @todo Use a standard rdf process, with no entities for bodies and targets.
     *
     * @deprecated Will be replaced by a new resource form, on the resource template base.
     *
     * @param array $data
     * @return array
     */
    public function divideMergedValues(array $data)
    {
        $plugins = $this->getServiceLocator()->get('ControllerPluginManager');
        /** @var \Generateur\Mvc\Controller\Plugin\DivideMergedValues $divideMergedValues */
        $divideMergedValues = $plugins->get('divideMergedValues');
        $resourceTemplate = $this->resourceTemplate();
        if ($resourceTemplate) {
            /** @var \Generateur\Mvc\Controller\Plugin\ResourceTemplateGenerationPartMap $resourceTemplateGenerationPartMap */
            $resourceTemplateGenerationPartMap = $plugins->get('resourceTemplateGenerationPartMap');
            $generationPartMap = $resourceTemplateGenerationPartMap($resourceTemplate->id());
        } else {
            $generationPartMap = [];
        }
        return $divideMergedValues($data, $generationPartMap);
    }
}

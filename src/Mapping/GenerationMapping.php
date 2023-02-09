<?php declare(strict_types=1);
namespace Generateur\Mapping;

use CSVImport\Mapping\AbstractResourceMapping;
use Omeka\Stdlib\Message;

class GenerationMapping extends AbstractResourceMapping
{
    protected $label = 'Generation data'; // @translate
    protected $resourceType = 'generations';

    protected function processGlobalArgs(): void
    {
        parent::processGlobalArgs();

        /** @var array $data */
        $data = &$this->data;

        // Set the default resource type as "generations".
        if (isset($this->args['column-resource_type'])) {
            $this->map['resourceType'] = $this->args['column-resource_type'];
            $data['resource_type'] = 'generations';
        }
    }

    protected function processCell($index, array $values): void
    {
        parent::processCell($index, $values);
        $this->processCellGeneration($index, $values);

        /** @var array $data */
        $data = &$this->data;

        if (isset($this->map['resourceType'][$index])) {
            $resourceType = reset($values);
            // Add some heuristic to avoid common issues.
            $resourceType = str_replace([' ', '_'], '', strtolower($resourceType));
            $resourceTypes = [
                'generations' => 'generations',
                'generation' => 'generations',
            ];
            if (isset($resourceTypes[$resourceType])) {
                $data['resource_type'] = $resourceTypes[$resourceType];
            } else {
                $this->logger->err(new Message('"%s" is not a valid resource type.', reset($values))); // @translate
                $this->setHasErr(true);
            }
        }
    }

    protected function processCellGeneration($index, array $values): void
    {
        $data = &$this->data;

        if (isset($this->map['generation'][$index])) {
            $identifierProperty = $this->map['generation'][$index];
            $resourceType = 'generations';
            $findResourceFromIdentifier = $this->findResourceFromIdentifier;
            foreach ($values as $identifier) {
                $resourceId = $findResourceFromIdentifier($identifier, $identifierProperty, $resourceType);
                if ($resourceId) {
                    $data['o:generation'][] = ['o:id' => $resourceId];
                } else {
                    $this->logger->err(new Message('"%s" (%s) is not a valid generation.', // @translate
                        $identifier, $identifierProperty));
                    $this->setHasErr(true);
                }
            }
        }
    }
}

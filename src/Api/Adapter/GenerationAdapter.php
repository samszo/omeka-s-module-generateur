<?php declare(strict_types=1);
namespace Generateur\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class GenerationAdapter extends AbstractResourceEntityAdapter
{
    use QueryDateTimeTrait;

    protected $generatables = [
        \Omeka\Entity\Item::class,
        // \Omeka\Entity\Media::class,
        // \Omeka\Entity\ItemSet::class,
        // \Generateur\Entity\Generation::class,
    ];

    protected $sortFields = [
        'id' => 'id',
        'is_public' => 'isPublic',
        'created' => 'created',
        'modified' => 'modified',
        'resource' => 'resource',
        'title' => 'title',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'title' => 'title',
        'created' => 'created',
        'modified' => 'modified',
        'is_public' => 'isPublic',
        'thumbnail' => 'thumbnail',
        'owner' => 'owner',
        'resource_class' => 'resourceClass',
        'resource_template' => 'resourceTemplate',
    ];

    public function getResourceName()
    {
        return 'generations';
    }

    public function getRepresentationClass()
    {
        return \Generateur\Api\Representation\GenerationRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Generateur\Entity\Generation::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        parent::buildQuery($qb, $query);

        if (isset($query['resource_id']) && is_numeric($query['resource_id'])) {
            $isOldOmeka = \Omeka\Module::VERSION < 2;
            $alias = $isOldOmeka ? $this->getEntityClass() : 'omeka_root';
            $expr = $qb->expr();

            $qb->andWhere($expr->eq(
                $alias . '.resource',
                $this->createNamedParameter($qb, $query['resource_id'])
            ));
        }

        // The Generations are not related to a site.

        $this->searchDateTime($qb, $query);
    }

    public function validateRequest(Request $request, ErrorStore $errorStore): void
    {
        $data = $request->getContent();
        if (empty($data['o:resource'])) {
            $errorStore->addError('o:resource', 'The resource must be set.'); // @translate
        }
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore): void
    {
        if (!$entity->getResource()) {
            $errorStore->addError('o:resource', 'A Generation must be linked to a resource.'); // @translate
        }
        parent::validateEntity($entity, $errorStore);
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        $data = $request->getContent();

        // The resource cannot be changed.
        if ($request->getOperation() === Request::CREATE) {
            if (isset($data['o:resource']['o:id'])) {
                $resource = $this->getAdapter('resources')
                    ->findEntity($data['o:resource']['o:id']);
                $entity->setResource($resource);
            }
        }

        parent::hydrate($request, $entity, $errorStore);
    }
}

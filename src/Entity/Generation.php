<?php declare(strict_types=1);
namespace Generateur\Entity;

use Omeka\Entity\Resource;

/**
 * @Entity
 */
class Generation extends Resource
{
    /**
     * @var int
     * @Id
     * @Column(type="integer")
     */
    protected $id;

    /**
     * @var \Omeka\Entity\Resource
     *
     * This relation is unidirectrional because it's not possible to modify the
     * doctrine annotation for the inverse side (inversedBy="resources").
     *
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Resource"
     * )
     * @JoinColumn(
     *      nullable=false,
     *      onDelete="CASCADE"
     * )
     */
    protected $resource;

    public function getResourceName()
    {
        return 'generations';
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param Resource $resource
     * @return self
     */
    public function setResource(Resource $resource)
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * @return \Omeka\Entity\Resource
     */
    public function getResource()
    {
        return $this->resource;
    }
}

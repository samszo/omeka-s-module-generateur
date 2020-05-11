<?php
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
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Resource",
     *     inversedBy="resources"
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

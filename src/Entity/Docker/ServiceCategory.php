<?php

namespace Dashtainer\Entity\Docker;

use Dashtainer\Entity;
use Dashtainer\Util;

use Doctrine\Common\Collections;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="docker_service_category")
 * @ORM\Entity()
 */
class ServiceCategory implements Util\HydratorInterface, Entity\EntityBaseInterface
{
    use Util\HydratorTrait;
    use Entity\EntityBaseTrait;

    /**
     * @ORM\Column(name="name", type="string", length=64, unique=true)
     */
    protected $name;

    /**
     * @ORM\Column(name="`order`", type="smallint")
     */
    protected $order;

    /**
     * @ORM\OneToMany(targetEntity="Dashtainer\Entity\Docker\ServiceType", mappedBy="category")
     * @ORM\OrderBy({"order" = "ASC"})
     */
    protected $types;

    public function __construct()
    {
        $this->types = new Collections\ArrayCollection();
    }

    public function getName() : ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    public function getOrder() : ?int
    {
        return $this->order;
    }

    /**
     * @param int $order
     * @return $this
     */
    public function setOrder(int $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @param ServiceType $serviceType
     * @return $this
     */
    public function addType(ServiceType $serviceType)
    {
        if ($this->types->contains($serviceType)) {
            return $this;
        }

        $this->types->add($serviceType);
        $serviceType->setCategory($this);

        return $this;
    }

    public function removeType(ServiceType $serviceType)
    {
        if (!$this->types->contains($serviceType)) {
            return;
        }

        $this->types->removeElement($serviceType);
        $serviceType->setCategory(null);
    }

    /**
     * @return ServiceType[]|Collections\ArrayCollection
     */
    public function getTypes()
    {
        return $this->types;
    }
}

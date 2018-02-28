<?php

namespace DashtainerBundle\Entity;

use DashtainerBundle\Util;

use Doctrine\Common\Collections;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="service_category")
 * @ORM\Entity(repositoryClass="DashtainerBundle\Repository\ServiceCategoryRepository")
 */
class ServiceCategory implements Util\HydratorInterface, EntityBaseInterface
{
    use Util\HydratorTrait;
    use EntityBaseTrait;

    /**
     * @ORM\OneToMany(targetEntity="DashtainerBundle\Entity\ServiceType", mappedBy="service_category")
     * @ORM\OrderBy({"order" = "ASC"})
     */
    protected $service_types;

    /**
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    protected $name;

    /**
     * @ORM\Column(name="`order`", type="smallint")
     */
    protected $order;

    public function __construct()
    {
        $this->service_types = new Collections\ArrayCollection();
    }

    /**
     * @param ServiceType $serviceType
     * @return $this
     */
    public function addServiceType(ServiceType $serviceType)
    {
        $this->service_types[] = $serviceType;

        return $this;
    }

    public function removeServiceType(ServiceType $serviceType)
    {
        $this->service_types->removeElement($serviceType);
    }

    /**
     * @return Service[]|Collections\ArrayCollection
     */
    public function getServiceTypes()
    {
        return $this->service_types;
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
}

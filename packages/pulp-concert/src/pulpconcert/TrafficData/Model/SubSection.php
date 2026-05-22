<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData\Model;

use OpenMapsight\pulpconcert\Model\AdditionalPropertiesTrait;
use OpenMapsight\pulpconcert\Model\DataObject;
use OpenMapsight\pulpconcert\Model\HasAdditionalPropertiesInterface;
use OpenMapsight\pulpconcert\Model\HasIdInterface;
use proj4php\Point;

class SubSection extends DataObject implements HasAdditionalPropertiesInterface, HasIdInterface
{
    use AdditionalPropertiesTrait;

    /** @var string|null $id */
    protected $id;

    /** @var string|null $name */
    protected $name;

    /** @var string|null $direction */
    protected $direction;

    /** @var Point[]|null $coordinates */
    protected $coordinates;

    /**
     * @return Point[]|null
     */
    public function getCoordinates()
    {
        return $this->coordinates;
    }

    /**
     * @param Point[]|null $coordinates
     */
    public function setCoordinates($coordinates): void
    {
        $this->coordinates = $coordinates;
    }

    /**
     * @return string|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string|null $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getDirection()
    {
        return $this->direction;
    }

    /**
     * @param string|null $direction
     */
    public function setDirection($direction): void
    {
        $this->direction = $direction;
    }

    public function getAllPropertiesAsArray(): array
    {
        $coordinates = is_array($this->getCoordinates()) ?
            array_map(static fn(Point $point): array => [$point->x, $point->y], $this->getCoordinates()) :
            null;

        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'direction' => $this->getDirection(),
            'coordinates' => $coordinates,
        ];
    }

    /**
     * Specify data which should be serialized to JSON
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), $this->getAllPropertiesAsArray());
    }
}

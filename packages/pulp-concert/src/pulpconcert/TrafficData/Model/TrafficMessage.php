<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData\Model;

use OpenMapsight\pulpconcert\Model\AdditionalPropertiesTrait;
use OpenMapsight\pulpconcert\Model\DataObject;
use OpenMapsight\pulpconcert\Model\HasAdditionalPropertiesInterface;
use OpenMapsight\pulpconcert\Model\HasIdInterface;

class TrafficMessage extends DataObject implements HasAdditionalPropertiesInterface, HasIdInterface
{
    use AdditionalPropertiesTrait;

    public const TIME_FORMAT = 'H:i:sP';

    /** @var string|null $id */
    protected $id;

    /** @var string|null $name */
    protected $name;

    /** @var string|null $description */
    protected $description;

    /** @var string|null $type */
    protected $type;

    /** @var string|null $subType */
    protected $subType;

    /** @var string|null $category */
    protected $category;

    /** @var string|null $severity */
    protected $severity;

    /** @var array|null $geometries */
    protected $geometries;

    /** @var string|null $roadName */
    protected $roadName;

    /** @var string[]|null $restrictions */
    protected $restrictions;

    /** @var string|null $totalValidityStart */
    protected $totalValidityStart;

    /** @var string|null $totalValidityEnd */
    protected $totalValidityEnd;

    /** @var string|null $dailyStartTime */
    protected $dailyStartTime;

    /** @var string|null $dailyEndTime */
    protected $dailyEndTime;

    /**
     * @return null|string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param null|string $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return null|string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param null|string $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return null|string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param null|string $description
     */
    public function setDescription($description): void
    {
        $this->description = $description;
    }

    /**
     * @return null|string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param null|string $type
     */
    public function setType($type): void
    {
        $this->type = $type;
    }

    /**
     * @return null|string
     */
    public function getSubType()
    {
        return $this->subType;
    }

    /**
     * @param null|string $subType
     */
    public function setSubType($subType): void
    {
        $this->subType = $subType;
    }

    /**
     * @return null|string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param null|string $category
     */
    public function setCategory($category): void
    {
        $this->category = $category;
    }

    /**
     * @return null|string
     */
    public function getSeverity()
    {
        return $this->severity;
    }

    /**
     * @param null|string $severity
     */
    public function setSeverity($severity): void
    {
        $this->severity = $severity;
    }

    /**
     * @return array|null
     */
    public function getGeometries()
    {
        return $this->geometries;
    }

    /**
     * @param array|null $geometries
     */
    public function setGeometries($geometries): void
    {
        $this->geometries = $geometries;
    }

    /**
     * @return string|null
     */
    public function getRoadName()
    {
        return $this->roadName;
    }

    /**
     * @param string|null $roadName
     */
    public function setRoadName($roadName): void
    {
        $this->roadName = $roadName;
    }

    /**
     * @return null|string[]
     */
    public function getRestrictions()
    {
        return $this->restrictions;
    }

    /**
     * @param null|string[] $restrictions
     */
    public function setRestrictions($restrictions): void
    {
        $this->restrictions = $restrictions;
    }

    /**
     * @return string|null
     */
    public function getTotalValidityStart()
    {
        return $this->totalValidityStart;
    }

    /**
     * @param string|null $totalValidityStart
     */
    public function setTotalValidityStart($totalValidityStart): void
    {
        $this->totalValidityStart = $totalValidityStart;
    }

    /**
     * @return string|null
     */
    public function getTotalValidityEnd()
    {
        return $this->totalValidityEnd;
    }

    /**
     * @param string|null $totalValidityEnd
     */
    public function setTotalValidityEnd($totalValidityEnd): void
    {
        $this->totalValidityEnd = $totalValidityEnd;
    }

    /**
     * @return string|null
     */
    public function getDailyStartTime()
    {
        return $this->dailyStartTime;
    }

    /**
     * @param string|null $dailyStartTime
     */
    public function setDailyStartTime($dailyStartTime): void
    {
        $this->dailyStartTime = $dailyStartTime;
    }

    /**
     * @return string|null
     */
    public function getDailyEndTime()
    {
        return $this->dailyEndTime;
    }

    /**
     * @param string|null $dailyEndTime
     */
    public function setDailyEndTime($dailyEndTime): void
    {
        $this->dailyEndTime = $dailyEndTime;
    }

    /**
     * @return array
     */
    public function getAllPropertiesAsArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'type' => $this->getType(),
            'subType' => $this->getSubType(),
            'category' => $this->getCategory(),
            'severity' => $this->getSeverity(),
            'geometries' => $this->getGeometries(),
            'roadName' => $this->getRoadName(),
            'restrictions' => $this->getRestrictions(),
            'totalValidityStart' => $this->getTotalValidityStart(),
            'totalValidityEnd' => $this->getTotalValidityEnd(),
            'dailyStartTime' => $this->getDailyStartTime(),
            'dailyEndTime' => $this->getDailyEndTime(),
            'additionalProperties' => $this->getAdditionalProperties(),
        ];
    }

    /**
     * Specify data which should be serialized to JSON
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return array data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), $this->getAllPropertiesAsArray());
    }
}

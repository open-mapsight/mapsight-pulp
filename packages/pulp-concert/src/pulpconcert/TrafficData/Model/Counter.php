<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData\Model;

use OpenMapsight\pulpconcert\Model\AdditionalPropertiesTrait;
use OpenMapsight\pulpconcert\Model\DataObject;
use OpenMapsight\pulpconcert\Model\HasAdditionalPropertiesInterface;
use OpenMapsight\pulpconcert\Model\HasIdInterface;

class Counter extends DataObject implements HasAdditionalPropertiesInterface, HasIdInterface
{
    use AdditionalPropertiesTrait;

    public const STATUS_UNKNOWN = 'Unbekannt';
    public const STATUS_OK = 'o.k.';
    public const STATUS_NOK = 'n.o.k.';
    public const STATUS_OFFLINE = 'Offline';

    public const TRAFFIC_SITUATION_UNKNOWN = 'Unbekannt';
    public const TRAFFIC_SITUATION_FREE = 'Frei';
    public const TRAFFIC_SITUATION_SLOW = 'Zaehfliessend';
    public const TRAFFIC_SITUATION_JAM = 'Stau';

    /** @var string|null $id */
    protected $id;

    /** @var string|null $name */
    protected $name;

    /** @var string|null $locationCity */
    protected $locationCity;

    /** @var string|null $locationStreet */
    protected $locationStreet;

    /** @var string|null $locationCrossingName */
    protected $locationCrossingName;

    /** @var int|null $numberOfLanes */
    protected $numberOfLanes;

    /** @var string $status */
    protected $status = self::STATUS_UNKNOWN;

    /** @var string $trafficSituation */
    protected $trafficSituation = self::TRAFFIC_SITUATION_UNKNOWN;

    /** @var CounterCountDatum[]|null $countData */
    protected $countData = [];

    /** @var null|string $timestamp */
    protected $timestamp;

    /** @var int|null $dataInterval */
    protected $dataInterval;

    /** @var null|string $derivedDataTimestamp */
    protected $derivedDataTimestamp;

    /** @var int|null $derivedDataInterval */
    protected $derivedDataInterval;

    /**
     * @param string $type
     *
     * @return CounterCountDatum|null
     */
    public function getCountDataForType($type)
    {
        foreach ($this->countData as $countDatum) {
            if ($countDatum->getType() === $type) {
                return $countDatum;
            }
        }

        return null;
    }

    /**
     * @param CounterCountDatum $countDatum
     */
    public function addCountDatum($countDatum): void
    {
        if (!is_array($this->countData)) {
            $this->countData = [];
        }

        $this->countData[] = $countDatum;
    }

    /**
     * @return CounterCountDatum[]
     */
    public function getCountData(): array
    {
        return $this->countData;
    }

    /**
     * @param CounterCountDatum[] $countData
     */
    public function setCountData($countData): void
    {
        $this->countData = $countData;
    }

    /**
     * @return null|string
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
     * @return null|string
     */
    public function getLocationCity()
    {
        return $this->locationCity;
    }

    /**
     * @param null|string $locationCity
     */
    public function setLocationCity($locationCity): void
    {
        $this->locationCity = $locationCity;
    }

    /**
     * @return null|string
     */
    public function getLocationStreet()
    {
        return $this->locationStreet;
    }

    /**
     * @param null|string $locationStreet
     */
    public function setLocationStreet($locationStreet): void
    {
        $this->locationStreet = $locationStreet;
    }

    /**
     * @return null|string
     */
    public function getLocationCrossingName()
    {
        return $this->locationCrossingName;
    }

    /**
     * @param null|string $locationCrossingName
     */
    public function setLocationCrossingName($locationCrossingName): void
    {
        $this->locationCrossingName = $locationCrossingName;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status): void
    {
        $this->status = $status;
    }

    /**
     * @return int|null
     */
    public function getNumberOfLanes()
    {
        return $this->numberOfLanes;
    }

    /**
     * @param int|null $numberOfLanes
     */
    public function setNumberOfLanes($numberOfLanes): void
    {
        $this->numberOfLanes = $numberOfLanes;
    }

    /**
     * @return string
     */
    public function getTrafficSituation(): string
    {
        return $this->trafficSituation;
    }

    /**
     * @param string $trafficSituation
     */
    public function setTrafficSituation($trafficSituation): void
    {
        $this->trafficSituation = $trafficSituation;
    }

    /**
     * @return null|string
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @param string $timestamp
     */
    public function setTimestamp($timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * @return int|null
     */
    public function getDataInterval()
    {
        return $this->dataInterval;
    }

    /**
     * @param int|null $dataInterval
     */
    public function setDataInterval($dataInterval): void
    {
        $this->dataInterval = $dataInterval;
    }

    /**
     * @return null|string
     */
    public function getDerivedDataTimestamp()
    {
        return $this->derivedDataTimestamp;
    }

    /**
     * @param null|string $derivedDataTimestamp
     */
    public function setDerivedDataTimestamp($derivedDataTimestamp): void
    {
        $this->derivedDataTimestamp = $derivedDataTimestamp;
    }

    /**
     * @return int|null
     */
    public function getDerivedDataInterval()
    {
        return $this->derivedDataInterval;
    }

    /**
     * @param int|null $derivedDataInterval
     */
    public function setDerivedDataInterval($derivedDataInterval): void
    {
        $this->derivedDataInterval = $derivedDataInterval;
    }

    public function getAllPropertiesAsArray(): array
    {
        $countData = is_array($this->getCountData()) ?
            array_map(static fn(CounterCountDatum $countDatum): array => $countDatum->getAllPropertiesAsArray(), $this->getCountData()) :
            null;

        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'locationCity' => $this->getLocationCity(),
            'locationStreet' => $this->getLocationStreet(),
            'locationCrossingName' => $this->getLocationCrossingName(),
            'status' => $this->getStatus(),
            'numberOfLanes' => $this->getNumberOfLanes(),
            'trafficSituation' => $this->getTrafficSituation(),
            'dataTimestamp' => $this->getTimestamp(),
            'dataInterval' => $this->getDataInterval(),
            'derivedDataTimestamp' => $this->getDerivedDataTimestamp(),
            'derivedDataInterval' => $this->getDerivedDataInterval(),
            'countData' => $countData,
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

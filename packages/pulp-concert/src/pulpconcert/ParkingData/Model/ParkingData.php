<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\ParkingData\Model;

use OpenMapsight\pulpconcert\Model\DataObject;
use OpenMapsight\pulpconcert\Model\HasRelatedIdInterface;

class ParkingData extends DataObject implements OccupancyParkingDataInterface, HasRelatedIdInterface
{
    public const TENDENCY_CONSTANT = 'constant';
    public const TENDENCY_DECREASING = 'decreasing';
    public const TENDENCY_INCREASING = 'increasing';

    public const OPENING_STATE_OPEN = 'open';
    public const OPENING_STATE_CLOSED = 'closed';

    public const SUB_TYPE_SHORT_TERM = 'shortterm';
    public const SUB_TYPE_LONG_TERM = 'longterm';
    public const SUB_TYPE_ALL = 'all';
    public const SUB_TYPE_OTHER = 'other';

    public const STATE_OKAY = 'o.k.';
    public const STATE_NOT_OKAY = 'n.o.k.';

    /** @var string|null $id */
    protected $id;

    /** @var string|null $id */
    protected $openingState;

    /** @var string|null $id */
    protected $state;

    /** @var int|null $capacity */
    protected $capacity;

    /** @var int|null $occupancy */
    protected $occupancy;

    /** @var int|null $trend */
    protected $trend;

    /** @var int|null $driveIn */
    protected $driveIn;

    /** @var int|null $driveOut */
    protected $driveOut;

    /** @var string|null $timestamp */
    protected $timestamp;

    /** @var SubTypeOccupancyParkingData[] $subTypeOccupancyData */
    protected $subTypeOccupancyData = [];

    /**
     * @return string|null
     */
    public function getRelatedId()
    {
        return $this->id;
    }

    /**
     * @return null|string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param null|string $state
     */
    public function setState($state): void
    {
        $this->state = $state;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), $this->getAllPropertiesAsArray());
    }

    /**
     * @return array
     */
    public function getAllPropertiesAsArray(): array
    {
        return [
            'id' => $this->getId(),
            'openingState' => $this->getOpeningState(),
            'occupancy' => $this->getOccupancy(),
            'occupancyRate' => $this->getOccupancyRate(),
            'subTypes' => $this->getSubTypes(),
            'subTypeOccupancyData' => array_map(static fn(SubTypeOccupancyParkingData $data) => $data->jsonSerialize(), $this->getSubTypeOccupancyData()),
            'capacity' => $this->getCapacity(),
            'free' => $this->getFree(),
            'driveIn' => $this->getDriveIn(),
            'driveOut' => $this->getDriveOut(),
            'trend' => $this->getTrend(),
            'timestamp' => $this->getTimestamp(),
        ];
    }

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
    public function getOpeningState()
    {
        return $this->openingState;
    }

    /**
     * @param null|string $openingState
     */
    public function setOpeningState($openingState): void
    {
        $this->openingState = $openingState;
    }

    /**
     * @return int|null
     */
    public function getOccupancy()
    {
        return $this->occupancy;
    }

    /**
     * @param int|null $occupancy
     */
    public function setOccupancy($occupancy): void
    {
        $this->occupancy = $occupancy;
    }

    /**
     * @return float|null
     */
    public function getOccupancyRate(): ?float
    {
        return $this->getCapacity() != 0 ? round(100 * ($this->getOccupancy() / $this->getCapacity())) : null;
    }

    /**
     * @return int|null
     */
    public function getCapacity()
    {
        return $this->capacity;
    }

    /**
     * @param int|null $capacity
     */
    public function setCapacity($capacity): void
    {
        $this->capacity = $capacity;
    }

    /**
     * @return string[]
     */
    public function getSubTypes(): array
    {
        return array_unique(
            array_map(
                static fn(SubTypeOccupancyParkingData $occupancyParkingData): string => $occupancyParkingData->getSubType(),
                array_filter(
                    $this->subTypeOccupancyData,
                    static fn(SubTypeOccupancyParkingData $occupancyParkingData): bool => $occupancyParkingData->getCapacity() > 0
                )
            )
        );
    }

    /**
     * @return SubTypeOccupancyParkingData[]
     */
    public function getSubTypeOccupancyData(): array
    {
        return $this->subTypeOccupancyData;
    }

    /**
     * @param SubTypeOccupancyParkingData[] $subTypeOccupancyData
     */
    public function setSubTypeOccupancyData($subTypeOccupancyData): void
    {
        $this->subTypeOccupancyData = $subTypeOccupancyData;
    }

    /**
     * @return int
     */
    public function getFree(): int
    {
        return $this->getCapacity() - $this->getOccupancy();
    }

    /**
     * @return int|null
     */
    public function getDriveIn()
    {
        return $this->driveIn;
    }

    /**
     * @param int|null $driveIn
     */
    public function setDriveIn($driveIn): void
    {
        $this->driveIn = $driveIn;
    }

    /**
     * @return int|null
     */
    public function getDriveOut()
    {
        return $this->driveOut;
    }

    /**
     * @param int|null $driveOut
     */
    public function setDriveOut($driveOut): void
    {
        $this->driveOut = $driveOut;
    }

    /**
     * @return int|null
     */
    public function getTrend()
    {
        return $this->trend;
    }

    /**
     * @param int|null $trend
     */
    public function setTrend($trend): void
    {
        $this->trend = $trend;
    }

    /**
     * @return string|null
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @param string|null $timestamp
     */
    public function setTimestamp($timestamp): void
    {
        $this->timestamp = $timestamp;
    }
}

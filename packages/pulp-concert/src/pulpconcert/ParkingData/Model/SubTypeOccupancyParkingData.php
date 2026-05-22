<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\ParkingData\Model;

use JsonSerializable;
use OpenMapsight\pulpconcert\Model\AllPropertiesAsArrayInterface;

class SubTypeOccupancyParkingData implements OccupancyParkingDataInterface, AllPropertiesAsArrayInterface, JsonSerializable
{
    /** @var string $id */
    protected $subType = ParkingData::SUB_TYPE_ALL;

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

    /** @var int $predictionInterval */
    protected $predictionInterval = 0;

    /**
     * @return string
     */
    public function getSubType(): string
    {
        return $this->subType;
    }

    /**
     * @param string $subType
     */
    public function setSubType($subType): void
    {
        $this->subType = $subType;
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
     * @return float
     */
    public function getOccupancyRate(): float|int
    {
        return $this->getCapacity() > 0 ? round(100 * ($this->getOccupancy() / $this->getCapacity())) : 0;
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
     * @return int
     */
    public function getFree(): int|float
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
     * @return int
     */
    public function getPredictionInterval(): int
    {
        return $this->predictionInterval;
    }

    /**
     * @param int $predictionInterval
     */
    public function setPredictionInterval(int $predictionInterval): void
    {
        $this->predictionInterval = $predictionInterval;
    }

    /**
     * @return array
     */
    public function getAllPropertiesAsArray(): array
    {
        return [
            'subType' => $this->getSubType(),
            'occupancy' => $this->getOccupancy(),
            'occupancyRate' => $this->getOccupancyRate(),
            'capacity' => $this->getCapacity(),
            'free' => $this->getFree(),
            'driveIn' => $this->getDriveIn(),
            'driveOut' => $this->getDriveOut(),
            'trend' => $this->getTrend(),
            'predictionInterval' => $this->getPredictionInterval(),
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->getAllPropertiesAsArray();
    }
}

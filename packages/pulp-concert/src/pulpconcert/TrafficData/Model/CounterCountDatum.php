<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData\Model;

use OpenMapsight\pulpconcert\Model\AllPropertiesAsArrayInterface;
use OpenMapsight\pulpconcert\Model\HasIdInterface;

class CounterCountDatum implements HasIdInterface, AllPropertiesAsArrayInterface
{
    public const TYPE_TOTAL = 'Gesamt';
    public const TYPE_LKW = 'Lkw';
    public const TYPE_LKW_WITH_TRAILER = 'LKWmitHaenger';
    public const TYPE_PKW = 'Pkw';
    public const TYPE_PKW_WITH_TRAILER = 'PKWmitHaenger';
    public const TYPE_BUS = 'Bus';
    public const TYPE_MOTOR_BIKE = 'Krad';
    public const TYPE_OTHER = 'Andere';
    public const TYPE_DELIVERY_TRUCK = 'Lieferwagen';
    public const TYPE_TRACTOR = 'SattelKFZ';

    /** @var string|null $type */
    protected $type = null;

    /** @var int|null $countValue */
    protected $countValue = null;

    /** @var int|null $type */
    protected $speed = null;

    /** @var int|null $occupancyRate */
    protected $occupancyRate = null;

    /**
     * @return null|string
     */
    public function getId()
    {
        return $this->getType();
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
     * @return int|null
     */
    public function getCountValue()
    {
        return $this->countValue;
    }

    /**
     * @param int|null $countValue
     */
    public function setCountValue($countValue): void
    {
        $this->countValue = $countValue;
    }

    /**
     * @return int|null
     */
    public function getOccupancyRate()
    {
        return $this->occupancyRate;
    }

    /**
     * @param int|null $occupancyRate
     */
    public function setOccupancyRate($occupancyRate): void
    {
        $this->occupancyRate = $occupancyRate;
    }

    /**
     * @return int|null
     */
    public function getSpeed()
    {
        return $this->speed;
    }

    /**
     * @param int|null $speed
     */
    public function setSpeed($speed): void
    {
        $this->speed = $speed;
    }

    public function getAllPropertiesAsArray(): array
    {
        return [
            'type' => $this->getType(),
            'countValue' => $this->getCountValue(),
            'occupancyRate' => $this->getOccupancyRate(),
            'speed' => $this->getSpeed(),
        ];
    }
}

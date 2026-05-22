<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\ParkingData\Model;

interface OccupancyParkingDataInterface
{
    /**
     * @return int|null
     */
    public function getCapacity();

    /**
     * @param int|null $capacity
     */
    public function setCapacity($capacity);

    /**
     * @return int|null
     */
    public function getOccupancy();

    /**
     * @param int|null $occupancy
     */
    public function setOccupancy($occupancy);

    /**
     * @return int|null
     */
    public function getTrend();

    /**
     * @param int|null $trend
     */
    public function setTrend($trend);

    /**
     * @return int|null
     */
    public function getDriveIn();

    /**
     * @param int|null $driveIn
     */
    public function setDriveIn($driveIn);

    /**
     * @return int|null
     */
    public function getDriveOut();

    /**
     * @param int|null $driveOut
     */
    public function setDriveOut($driveOut);

    /**
     * @return int|null
     */
    public function getFree();

    /**
     * @return float|null
     */
    public function getOccupancyRate();
}

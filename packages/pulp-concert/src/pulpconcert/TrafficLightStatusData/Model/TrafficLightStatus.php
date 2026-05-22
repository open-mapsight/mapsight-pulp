<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficLightStatusData\Model;

use OpenMapsight\pulpconcert\Model\DataObject;
use OpenMapsight\pulpconcert\Model\HasRelatedIdInterface;

class TrafficLightStatus extends DataObject implements HasRelatedIdInterface
{
    /**
     * @var string|null
     */
    protected $id;

    /**
     * @var string|null
     */
    protected $status;

    /**
     * @var int|null
     */
    protected $statusCode;

    /**
     * @var int|null
     */
    protected $signalPlan;

    /**
     * @return string|null
     */
    public function getRelatedId()
    {
        return $this->getId();
    }

    /**
     * @return string|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id): void
    {
        $this->id = $id;
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
            'status' => $this->getStatus(),
            'statusCode' => $this->getStatusCode(),
            'signalPlan' => $this->getSignalPlan(),
            'hasFailure' => $this->getHasFailure(),
        ];
    }

    /**
     * @return string|null
     */
    public function getStatus()
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
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param int $statusCode
     */
    public function setStatusCode($statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @return int|null
     */
    public function getSignalPlan()
    {
        return $this->signalPlan;
    }

    /**
     * @param int $signalPlan
     */
    public function setSignalPlan($signalPlan): void
    {
        $this->signalPlan = $signalPlan;
    }

    /**
     * @return bool
     */
    public function getHasFailure(): bool
    {
        if ($this->getStatusCode() === 13) {
            return true;
        }
        return $this->getStatusCode() === 14;
    }
}

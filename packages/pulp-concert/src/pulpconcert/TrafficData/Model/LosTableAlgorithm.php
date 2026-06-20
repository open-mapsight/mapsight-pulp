<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData\Model;

use JsonSerializable;
use OpenMapsight\pulpconcert\Model\AllPropertiesAsArrayInterface;

class LosTableAlgorithm implements AllPropertiesAsArrayInterface, JsonSerializable
{
    /** @var string $identifier */
    protected $identifier;
    /** @var boolean $isEnabled */
    protected $isEnabled;

    protected $oThresholds = [];

    /** @var number[] $volumeThresholds */
    protected $volumeThresholds = [];
    /** @var number[] $speedThresholds */
    protected $speedThresholds = [];
    /** @var number[] $losMatrix */
    protected $losMatrix;
    // NOTE: Hysteresis are ignored (for now)

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     */
    public function setIdentifier($identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * @param bool $isEnabled
     */
    public function setIsEnabled($isEnabled): void
    {
        $this->isEnabled = $isEnabled;
    }

    /**
     * @return number[]
     */
    public function getSpeedThresholds(): array
    {
        return $this->speedThresholds;
    }

    /**
     * @param number[] $speedThresholds
     */
    public function setSpeedThresholds($speedThresholds): void
    {
        $this->speedThresholds = $speedThresholds;
    }

    /**
     * @return number[]
     */
    public function getOThresholds(): array
    {
        return $this->oThresholds;
    }

    /**
     * @param number[] $oThresholds
     */
    public function setOThresholds($oThresholds): void
    {
        $this->oThresholds = $oThresholds;
    }

    /**
     * @return number[]
     */
    public function getVolumeThresholds(): array
    {
        return $this->volumeThresholds;
    }

    /**
     * @param number[] $volumeThresholds
     */
    public function setVolumeThresholds($volumeThresholds): void
    {
        $this->volumeThresholds = $volumeThresholds;
    }

    /**
     * @return number[]
     */
    public function getLosMatrix(): array
    {
        return $this->losMatrix;
    }

    /**
     * @param number[] $losMatrix
     */
    public function setLosMatrix($losMatrix): void
    {
        $this->losMatrix = $losMatrix;
    }

    public function getAllPropertiesAsArray(): array
    {
        return [
            'identifier' => $this->getIdentifier(),
            'isEnabled' => $this->isEnabled(),
            'oThresholds' => $this->getOThresholds(),
            'speedThresholds' => $this->getSpeedThresholds(),
            'volumeThresholds' => $this->getVolumeThresholds(),
            'losMatrix' => $this->getLosMatrix(),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->getAllPropertiesAsArray();
    }
}

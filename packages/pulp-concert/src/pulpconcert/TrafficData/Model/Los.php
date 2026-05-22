<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData\Model;

use OpenMapsight\pulpconcert\Model\DataObject;
use OpenMapsight\pulpconcert\Model\HasIdInterface;
use OpenMapsight\pulpconcert\Model\HasRelatedIdInterface;

class Los extends DataObject implements HasIdInterface, HasRelatedIdInterface
{
    public const LOS_UNKNOWN = 'unknown';
    public const LOS_FREE = 'free';
    public const LOS_SLOW = 'slow';
    public const LOS_SLOWER = 'slower';
    public const LOS_SLOWEST = 'slowest';
    public const LOS_JAM = 'jam';

    public const LOS_VALUE_FREE = 0;
    public const LOS_VALUE_SLOW = 1;
    public const LOS_VALUE_SLOWER = 2;
    public const LOS_VALUE_SLOWEST = 3;
    public const LOS_VALUE_JAM = 4;
    public const LOS_VALUE_UNKNOWN = 5;

    /** @var string|null $id */
    protected $id = null;

    /** @var string[]|null $timeline */
    protected $timeline = null;

    /** @var int|null $los */
    protected $los = null;

    /** @var int|null $predictedLos */
    protected $predictedLos = null;

    /** @var int|null $quality */
    protected $quality = null;

    /** @var string|null $predictionTimestamp */
    protected $predictionTimestamp = null;

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
     * @return string[]|null
     */
    public function getTimeline()
    {
        return $this->timeline;
    }

    /**
     * @param string[]|null $timeline
     */
    public function setTimeline($timeline): void
    {
        $this->timeline = $timeline;
    }

    /**
     * @return int|null
     */
    public function getLos()
    {
        return $this->los;
    }

    /**
     * @param int|null $los
     */
    public function setLos($los): void
    {
        $this->los = $los;
    }

    /**
     * @return string
     */
    public function getLosString(): string
    {
        return match ((int) $this->los) {
            self::LOS_VALUE_FREE => self::LOS_FREE,
            self::LOS_VALUE_SLOW => self::LOS_SLOW,
            self::LOS_VALUE_SLOWER => self::LOS_SLOWER,
            self::LOS_VALUE_SLOWEST => self::LOS_SLOWEST,
            self::LOS_VALUE_JAM => self::LOS_JAM,
            default => self::LOS_UNKNOWN,
        };
    }

    /**
     * @return int|null
     */
    public function getPredictedLos()
    {
        return $this->predictedLos;
    }

    /**
     * @param int|null $predictedLos
     */
    public function setPredictedLos($predictedLos): void
    {
        $this->predictedLos = $predictedLos;
    }

    /**
     * @return string|null
     */
    public function getPredictionTimestamp()
    {
        return $this->predictionTimestamp;
    }

    /**
     * @param string|null $predictionTimestamp
     */
    public function setPredictionTimestamp($predictionTimestamp): void
    {
        $this->predictionTimestamp = $predictionTimestamp;
    }

    /**
     * @return int|null
     */
    public function getQuality()
    {
        return $this->quality;
    }

    /**
     * @param int|null $quality
     */
    public function setQuality($quality): void
    {
        $this->quality = $quality;
    }

    public function getAllPropertiesAsArray(): array
    {
        return [
            'id' => $this->getId(),
            'timeline' => $this->getTimeline(),
            'los' => $this->getLos(),
            'losString' => $this->getLosString(),
            'predictedLos' => $this->getPredictedLos(),
            'predictionTimestamp' => $this->getPredictionTimestamp(),
            'quality' => $this->getQuality(),
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

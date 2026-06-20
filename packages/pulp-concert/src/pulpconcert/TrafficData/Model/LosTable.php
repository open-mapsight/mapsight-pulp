<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData\Model;

use JsonSerializable;
use OpenMapsight\pulpconcert\Model\AllPropertiesAsArrayInterface;

class LosTable implements AllPropertiesAsArrayInterface, JsonSerializable
{
    /** @var string $identifier */
    protected $identifier;

    /** @var LosTableAlgorithm[] $algorithms */
    protected $algorithms;

    /**
     * @param      $name
     * @param bool $includeDisabled
     *
     * @return LosTableAlgorithm|null
     */
    public function getAlgorithmByName($name, $includeDisabled = false)
    {
        foreach ($this->algorithms as $algorithm) {
            if ($includeDisabled || ($algorithm->isEnabled() && $algorithm->getIdentifier() === $name)) {
                return $algorithm;
            }
        }

        return null;
    }

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
     * @return LosTableAlgorithm[]
     */
    public function getAlgorithms(): array
    {
        return $this->algorithms;
    }

    /**
     * @param LosTableAlgorithm[] $algorithms
     */
    public function setAlgorithms($algorithms): void
    {
        $this->algorithms = $algorithms;
    }

    public function getAllPropertiesAsArray(): array
    {
        return [
            'identifier' => $this->getIdentifier(),
            'algorithms' => $this->getAlgorithms(),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->getAllPropertiesAsArray();
    }
}

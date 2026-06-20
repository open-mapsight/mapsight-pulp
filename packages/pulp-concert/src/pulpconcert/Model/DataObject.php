<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\Model;

use JsonSerializable;

class DataObject implements AllPropertiesAsArrayInterface, JsonSerializable
{
    protected $storeTimestamp;
    protected $objectState;
    protected $identifier;
    protected $source;
    protected $__data;

    /**
     * @return mixed
     */
    public function getStoreTimestamp()
    {
        return $this->storeTimestamp;
    }

    /**
     * @param mixed $storeTimestamp
     */
    public function setStoreTimestamp($storeTimestamp): void
    {
        $this->storeTimestamp = $storeTimestamp;
    }

    /**
     * @return mixed
     */
    public function getObjectState()
    {
        return $this->objectState;
    }

    /**
     * @param mixed $objectState
     */
    public function setObjectState($objectState): void
    {
        $this->objectState = $objectState;
    }

    /**
     * @return mixed
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @param mixed $identifier
     */
    public function setIdentifier($identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @param string $source
     */
    public function setSource($source): void
    {
        $this->source = $source;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->__data;
    }

    /**
     * @param mixed $_data
     */
    public function setData($_data): void
    {
        $this->__data = $_data;
    }

    /** @return array */
    public function getAllPropertiesAsArray(): array
    {
        return [
            'storeTimestamp' => $this->getStoreTimestamp(),
            'objectState' => $this->getObjectState(),
            'identifier' => $this->getIdentifier(),
            'source' => $this->getSource(),
            '__data' => $this->getData(),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->getAllPropertiesAsArray();
    }
}

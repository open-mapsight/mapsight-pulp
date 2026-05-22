<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\Model;

trait AdditionalPropertiesTrait
{
    /** @var array|null $additionalProperties */
    protected $additionalProperties;

    /**
     * @return array|null
     */
    public function getAdditionalProperties()
    {
        return $this->additionalProperties;
    }

    /**
     * @param array|null $additionalProperties
     */
    public function setAdditionalProperties($additionalProperties): void
    {
        $this->additionalProperties = $additionalProperties;
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function getAdditionalProperty($key): null
    {
        return $this->additionalProperties !== null && isset($this->additionalProperties[$key]) ? $this->additionalProperties[$key] : null;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function setAdditionalProperty($key, $value): void
    {
        if ($this->additionalProperties === null) {
            $this->additionalProperties = [];
        }

        $this->additionalProperties[$key] = $value;
    }
}

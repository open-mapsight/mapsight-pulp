<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\Model;

interface HasAdditionalPropertiesInterface
{
    /**
     * @return array|null
     */
    public function getAdditionalProperties();

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function getAdditionalProperty($key);

    /**
     * @param array|null $additionalProperties
     */
    public function setAdditionalProperties($additionalProperties);

    /**
     * @param string $key
     * @param mixed $value
     */
    public function setAdditionalProperty($key, $value);
}

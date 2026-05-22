<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert;

use JsonSerializable;
use OpenMapsight\pulp\File;

abstract class AbstractMergeIntoGeoJSONHandler extends AbstractMergeIntoHandler
{
    protected $PROPERTY_KEY_EXTERNAL_ID = 'externalId';
    protected $propertyWhitelist = [];

    protected function loopFeatures($file, $data)
    {
        /** @var array $json */
        $json = &$file->content;

        if (!$json['features']) {
            return $file;
        }


        foreach ($json['features'] as &$feature) {
            $this->handleFeature($feature, $file, $data);
        }

        return $file;
    }

    protected function getFeatureExternalId($targetObject)
    {
        return $targetObject['properties'][$this->PROPERTY_KEY_EXTERNAL_ID] ?? null;
    }

    /**
     * @param JsonSerializable $featureData
     * @param array $targetObject
     * @param File $file
     * @param $data
     */
    protected function mergeFeatureProperties($featureData, &$targetObject, &$file, $data)
    {
        $properties = $featureData->jsonSerialize();

        // merge whitelisted properties
        $targetObject['properties'] = array_merge(
            $targetObject['properties'],
            array_intersect_key($properties, array_flip($this->propertyWhitelist))
        );
    }
}

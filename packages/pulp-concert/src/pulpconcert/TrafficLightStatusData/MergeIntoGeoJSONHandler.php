<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficLightStatusData;

use OpenMapsight\pulpconcert\AbstractMergeIntoGeoJSONHandler;
use OpenMapsight\pulpconcert\TrafficLightStatusData\Model\TrafficLightStatus;

class MergeIntoGeoJSONHandler extends AbstractMergeIntoGeoJSONHandler
{
    protected $PARAMETER_DATA_PULP = 'trafficLightStatusDataPulp';

    protected $propertyWhitelist = [
        'hasFailure',
        'signalPlan',
        'status',
        'statusCode',
    ];

    /**
     * @param TrafficLightStatus $featureData
     * @return bool
     */
    protected function isFeatureStatusOkay($featureData): bool
    {
        return true; // TODO: Check if status is really always okay!
    }

    /**
     * @param TrafficLightStatus $featureData
     * @return int
     */
    protected function getFeatureTimestamp($featureData): int
    {
        return time(); // TODO: Check if timestamp is actually available!
    }
}

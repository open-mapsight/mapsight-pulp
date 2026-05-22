<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\ParkingData;

use OpenMapsight\pulpconcert\AbstractMergeIntoGeoJSONHandler;
use OpenMapsight\pulpconcert\ParkingData\Model\ParkingData;
use OpenMapsight\pulpconcert\Utils;

class MergeIntoGeoJSONHandler extends AbstractMergeIntoGeoJSONHandler
{
    protected $PARAMETER_DATA_PULP = 'parkingDataPulp';

    protected $propertyWhitelist = [
        'capacity',
        'occupancy',
        'occupancyRate',
        'free',
        'timestamp',
        'trend',
        'state',
        'openingState',
        'subTypes',
    ];

    /**
     * @param ParkingData $featureData
     *
     * @return bool
     */
    protected function isFeatureStatusOkay($featureData): bool
    {
        return $featureData->getState() === ParkingData::STATE_OKAY;
    }

    /**
     * @param ParkingData $featureData
     *
     * @return int
     */
    protected function getFeatureTimestamp($featureData): int
    {
        return Utils::parsePulpDateTimeObject($featureData->getTimestamp())->getTimestamp();
    }
}

<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulpconcert\ParkingData\MergeIntoGeoJSONHandler;
use OpenMapsight\pulpconcert\ParkingData\MergeIntoKmlHandler;
use OpenMapsight\pulpconcert\ParkingData\ParseParkingDataHandler;
use OpenMapsight\pulpconcert\TrafficData\MergeCountersIntoGeoJSONHandler;
use OpenMapsight\pulpconcert\TrafficData\MergeLosHandler;
use OpenMapsight\pulpconcert\TrafficData\ParseCounterDataHandler;
use OpenMapsight\pulpconcert\TrafficData\ParseCountersHandler;
use OpenMapsight\pulpconcert\TrafficData\ParseLevelOfServiceTableHandler;
use OpenMapsight\pulpconcert\TrafficData\ParseLosHandler;
use OpenMapsight\pulpconcert\TrafficData\ParseSubSectionDescriptionsHandler;
use OpenMapsight\pulpconcert\TrafficData\ParseTrafficMessagesHandler;
use OpenMapsight\pulpconcert\TrafficData\SubSectionToGeoJSONHandler;
use OpenMapsight\pulpconcert\TrafficData\TrafficMessagsToGeoJSONHandler;
use OpenMapsight\pulpconcert\TrafficLightStatusData\ParseTrafficLightStatusDataHandler;

class PulpConcert
{
    // Parking Data
    public static function parseParkingData(): ParseParkingDataHandler
    {
        return new ParseParkingDataHandler();
    }

    public static function mergeParkingDataIntoKml(
        Pulp $parkingDataPulp,
        bool $mergeNotOkay = false,
        int $maxAge = -1
    ): MergeIntoKmlHandler {
        return new MergeIntoKmlHandler($parkingDataPulp, $mergeNotOkay, $maxAge);
    }

    public static function mergeParkingDataIntoGeoJSON(
        Pulp $parkingDataPulp,
        bool $mergeNotOkay = false,
        int $maxAge = -1
    ): MergeIntoGeoJSONHandler {
        return new MergeIntoGeoJSONHandler($parkingDataPulp, $mergeNotOkay, $maxAge);
    }

    // Traffic Data
    public static function parseTrafficDataSubSectionDescriptions(): ParseSubSectionDescriptionsHandler
    {
        return new ParseSubSectionDescriptionsHandler();
    }

    public static function trafficDataSubSectionToGeoJSON(): SubSectionToGeoJSONHandler
    {
        return new SubSectionToGeoJSONHandler();
    }

    public static function parseTrafficDataLos(): ParseLosHandler
    {
        return new ParseLosHandler();
    }

    public static function mergeTrafficDataLos($losPulp): MergeLosHandler
    {
        return new MergeLosHandler($losPulp);
    }

    public static function parseTrafficMessages(): ParseTrafficMessagesHandler
    {
        return new ParseTrafficMessagesHandler();
    }

    public static function trafficMessagesToGeoJSON(): TrafficMessagsToGeoJSONHandler
    {
        return new TrafficMessagsToGeoJSONHandler();
    }

    public static function parseTrafficCounters(
        Pulp $counterDataPulp = null,
        Pulp $counterDerivedDataPulp = null
    ): ParseCountersHandler {
        return new ParseCountersHandler($counterDataPulp, $counterDerivedDataPulp);
    }

    public static function parseTrafficCounterData(): ParseCounterDataHandler
    {
        return new ParseCounterDataHandler();
    }

    public static function mergeTrafficCountersIntoGeoJSON(
        Pulp $counterDataPulp,
        Pulp $losTablesPulp,
        bool $mergeNotOkay = true,
        int $maxAge = -1
    ): MergeCountersIntoGeoJSONHandler {
        return new MergeCountersIntoGeoJSONHandler($counterDataPulp, $losTablesPulp, $mergeNotOkay, $maxAge);
    }

    // Traffic Light Status Data
    public static function parseTrafficLightStatusData(): ParseTrafficLightStatusDataHandler
    {
        return new ParseTrafficLightStatusDataHandler();
    }

    public static function mergeTrafficLightStatusDataIntoGeoJSON(
        Pulp $trafficLightStatusPulp
    ): pulpconcert\TrafficLightStatusData\MergeIntoGeoJSONHandler {
        return new pulpconcert\TrafficLightStatusData\MergeIntoGeoJSONHandler($trafficLightStatusPulp);
    }

    public static function parseTrafficLevelOfServiceTable(): ParseLevelOfServiceTableHandler
    {
        return new ParseLevelOfServiceTableHandler();
    }
}

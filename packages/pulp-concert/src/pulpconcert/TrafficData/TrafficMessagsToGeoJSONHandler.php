<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData;

use DateTime;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulpconcert\TrafficData\Model\TrafficMessage;

class TrafficMessagsToGeoJSONHandler extends AbstractHandler
{
    private function mapFeature(TrafficMessage $trafficMessage): array
    {
        $feature = [
            'type' => 'Feature',
            'id' => $trafficMessage->getId(),
            'geometry' => [
                'type' => 'GeometryCollection',
                'geometries' => $trafficMessage->getGeometries(),
            ],
        ];

        $feature['properties'] = $trafficMessage->getAdditionalProperties();
        $feature['properties'] ??= [];

        if ($trafficMessage->getName() !== null) {
            $feature['properties']['name'] = $trafficMessage->getName();
        }

        if ($trafficMessage->getDescription() !== null) {
            $feature['properties']['description'] = $trafficMessage->getDescription();
        }

        if ($trafficMessage->getSubType() !== null) {
            $feature['properties']['subType'] = $trafficMessage->getSubType();
        }

        if ($trafficMessage->getCategory() !== null) {
            $feature['properties']['category'] = $trafficMessage->getCategory();
        }

        if ($trafficMessage->getRoadName() !== null) {
            $feature['properties']['roadName'] = $trafficMessage->getRoadName();
        }

        if ($trafficMessage->getRestrictions() !== null) {
            $feature['properties']['restrictions'] = implode(', ', $trafficMessage->getRestrictions());
        }

        if ($trafficMessage->getId() !== null) {
            $feature['properties']['id'] = $trafficMessage->getId();
        }

        $validityStart = DateTime::createFromFormat(DateTime::ATOM, $trafficMessage->getTotalValidityStart());
        $validityEnd = DateTime::createFromFormat(DateTime::ATOM, $trafficMessage->getTotalValidityEnd());

        $feature['when'] = [
            'start' => $validityStart ? $validityStart->format(DateTime::ISO8601) : null, // TODO: Use ATOM instead?!
            'end' => $validityEnd ? $validityEnd->format(DateTime::ISO8601) : null, // TODO: Use ATOM instead?!
            'dailyStartTime' => $trafficMessage->getDailyStartTime(),
            'dailyEndTime' => $trafficMessage->getDailyEndTime(),
        ];

        return $feature;
    }

    public function onFile(File $file): void
    {
        /** @var array $data */
        $data = $file->content;

        $features = array_map($this->mapFeature(...), $data);
        $file->content = [
            'type' => 'FeatureCollection',
            'crs' => ['type' => 'EPSG', 'properties' => ['code' => '4326']],
            'features' => $features,
        ];
        $this->pushFile($file);
    }
}

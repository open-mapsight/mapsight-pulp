<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulpconcert\TrafficData\Model\SubSection;

class SubSectionToGeoJSONHandler extends AbstractHandler
{
    private function mapFeature(SubSection $subSection): array
    {
        $properties = $subSection->getAdditionalProperties();
        $properties ??= [];

        if ($subSection->getName() !== null) {
            $properties['name'] = $subSection->getName();
        }

        if ($subSection->getDirection() !== null) {
            $properties['direction'] = $subSection->getDirection();
        }

        if ($subSection->getId() !== null) {
            $properties['id'] = $subSection->getId();
        }

        //if ($subSection->getStoreTimestamp() != null) {
        //	$properties['storeTimestamp'] = $subSection->getStoreTimestamp();
        //}

        //if ($subSection->getObjectState() != null) {
        //	$properties['state'] = $subSection->getObjectState();
        //}

        return [
            'type' => 'Feature',
            'id' => $subSection->getId(),
            'properties' => $properties,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $subSection->getCoordinates(),
            ],
        ];
    }

    /**
     * @param File $file
     */
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

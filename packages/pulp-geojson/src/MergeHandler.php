<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use OpenMapsight\GeoJsonReproject;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class MergeHandler extends AbstractHandler
{
    private ?string $projection = null;
    private array $features = [];

    protected function getConstructorParamDefs(): array
    {
        return [
            'fileName',
            [
                'key' => 'options',
                'default' => [
                    'includeSourceInformation' => false,
                ],
                'handler' => 'merge',
            ],
        ];
    }

    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertGeoJSONFile($file);

        $projection = GeoJsonReproject::getProjection($file->content);

        if ($this->projection === null) {
            $this->projection = $projection;
        }

        $data = $file->content;

        if ($this->projection !== $projection) {
            $data = GeoJsonReproject::reproject($data, $this->projection);
        }

        $features = Utils::extractFeatures($data);

        if ($this->cp->options['includeSourceInformation'] ?? false === true) {
            $features = array_map(static function (array $feature) use ($file): array {
                $feature['properties']['source'] = $file->fileName;

                return $feature;
            }, $features);
        }

        $this->features = array_merge($this->features, $features);
    }

    public function onEnd(): void
    {
        $data = [
            'type' => 'FeatureCollection',
            'features' => $this->features,
        ];
        $data = GeoJsonReproject::setProjection($data, $this->projection);

        $file = new File($this->cp->fileName);
        $file->content = $data;
        $this->pushFile($file);
    }
}

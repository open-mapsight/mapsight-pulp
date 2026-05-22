<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use OpenMapsight\GeoJsonReproject;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class SplitHandler extends AbstractHandler
{
    protected array $result = [];
    protected ?string $projection = null;

    protected function getConstructorParamDefs(): array
    {
        return ['cb'];
    }

    protected function createFile($fileName): void
    {
        $this->result[$fileName] = GeoJsonReproject::setProjection(
            [
                'type' => 'FeatureCollection',
                'features' => [],
            ],
            $this->projection
        );
    }

    protected function addFeatureToFile($fileName, $feature): void
    {
        if (!array_key_exists($fileName, $this->result)) {
            $this->createFile($fileName);
        }

        $this->result[$fileName]['features'][] = $feature;
    }

    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertFile($file);

        /** @var array $data */
        $data = $file->content;
        $this->projection = GeoJsonReproject::getProjection($data);

        foreach ($data['features'] as $feature) {
            $fileNames = $this->cp->cb($feature);

            if (is_string($fileNames)) {
                $fileNames = [$fileNames];
            }

            foreach ($fileNames as $fileName) {
                $this->addFeatureToFile($fileName, $feature);
            }
        }
    }

    public function onEnd(): void
    {
        foreach ($this->result as $fileName => $content) {
            $file = new File($fileName);
            $file->content = $content;
            $this->pushFile($file);
        }
    }
}

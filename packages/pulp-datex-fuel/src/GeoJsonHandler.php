<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexfuel;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use RuntimeException;

class GeoJsonHandler extends AbstractHandler
{
    /** @var list<array<string, mixed>> */
    private array $publications = [];

    protected function getConstructorParamDefs(): array
    {
        return ['bbox', 'sourceUrl', 'sourceName', 'documentationUrl', 'publicSourceUrl', 'options'];
    }

    public function onFile(File $file): void
    {
        $this->publications[] = self::publicationFromFile($file);
    }

    public function onEnd(): void
    {
        $builder = new DatexFuelStationsBuilder(
            $this->cp->bbox,
            $this->cp->sourceUrl ?? '',
            $this->cp->sourceName ?? 'DATEX Fuel',
            $this->cp->documentationUrl,
            $this->cp->publicSourceUrl
        );

        $features = [];
        foreach ($this->publications as $publication) {
            $features = array_merge($features, $builder->featuresFromPublication($publication));
        }

        usort($features, static fn(array $a, array $b): int => strnatcmp(
            (string) ($a['properties']['name'] ?? ''),
            (string) ($b['properties']['name'] ?? '')
        ));

        $file = new File('datex-fuel-stations.geojson');
        $file->content = [
            'type' => 'FeatureCollection',
            'features' => $features,
            'source' => [
                'name' => $this->cp->sourceName ?? 'DATEX Fuel',
                'url' => $this->cp->publicSourceUrl ?? $this->cp->sourceUrl,
                'documentationUrl' => $this->cp->documentationUrl,
                'bbox' => $this->cp->bbox,
            ],
        ];

        $this->pushFile($file);
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicationFromFile(File $file): array
    {
        $content = $file->content;
        if (is_array($content)) {
            return DatexFuelXml::publication($content, $file->fileName);
        }
        if (!is_string($content) || $content === '') {
            throw new RuntimeException('DATEX Fuel publication "' . $file->fileName . '" is empty');
        }

        return DatexFuelXml::publication($content, $file->fileName);
    }
}

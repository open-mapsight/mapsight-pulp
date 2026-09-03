<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexroadworks;

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
        $builder = new DatexRoadworksBuilder(
            $this->cp->bbox,
            $this->cp->sourceUrl ?? '',
            $this->cp->sourceName ?? 'DATEX Roadworks',
            $this->cp->documentationUrl,
            $this->cp->publicSourceUrl,
            isset($this->cp->options['feedKind']) ? (string) $this->cp->options['feedKind'] : 'ald',
        );

        $features = [];
        foreach ($this->publications as $publication) {
            $features = array_merge($features, $builder->featuresFromPublication($publication));
        }

        usort($features, static fn(array $a, array $b): int => strnatcmp(
            (string) ($a['properties']['name'] ?? ''),
            (string) ($b['properties']['name'] ?? '')
        ));

        $file = new File('datex-roadworks.geojson');
        $file->content = [
            'type' => 'FeatureCollection',
            'features' => $features,
            'source' => [
                'name' => $this->cp->sourceName ?? 'DATEX Roadworks',
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
            return DatexRoadworksXml::publication($content, $file->fileName);
        }
        if (!is_string($content) || $content === '') {
            throw new RuntimeException('DATEX roadworks publication "' . $file->fileName . '" is empty');
        }

        return DatexRoadworksXml::publication($content, $file->fileName);
    }
}

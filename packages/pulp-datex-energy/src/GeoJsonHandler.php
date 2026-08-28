<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexenergy;

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
        $builder = new DatexEnergySitesBuilder(
            $this->cp->bbox,
            $this->cp->sourceUrl ?? '',
            $this->cp->sourceName ?? 'DATEX Energy',
            $this->cp->documentationUrl,
            $this->cp->publicSourceUrl,
            isset($this->cp->options['preferredLang']) ? (string) $this->cp->options['preferredLang'] : null
        );

        $features = [];
        foreach ($this->publications as $publication) {
            $features = array_merge($features, $builder->featuresFromPublication($publication));
        }

        usort($features, static fn(array $a, array $b): int => strnatcmp(
            (string) ($a['properties']['name'] ?? ''),
            (string) ($b['properties']['name'] ?? '')
        ));

        $file = new File('datex-energy-sites.geojson');
        $file->content = [
            'type' => 'FeatureCollection',
            'features' => $features,
            'source' => [
                'name' => $this->cp->sourceName ?? 'DATEX Energy',
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
            return $content;
        }
        if (!is_string($content) || $content === '') {
            throw new RuntimeException('DATEX Energy publication "' . $file->fileName . '" is empty');
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('DATEX Energy publication "' . $file->fileName . '" is not valid JSON', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('DATEX Energy publication "' . $file->fileName . '" must decode to an object');
        }

        return $decoded;
    }
}

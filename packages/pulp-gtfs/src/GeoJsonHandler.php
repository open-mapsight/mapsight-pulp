<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgtfs;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use RuntimeException;

class GeoJsonHandler extends AbstractHandler
{
    private const DEFAULT_FILES = [
        'routes' => 'routes.txt',
        'stops' => 'stops.txt',
        'stopTimes' => 'stop_times.txt',
        'trips' => 'trips.txt',
        'shapes' => 'shapes.txt',
    ];

    /** @var array<string, File> */
    private array $filesByGtfsName = [];

    protected function getConstructorParamDefs(): array
    {
        return ['type', 'sourceUrl', 'bbox', 'departuresBaseUrl', 'sourceName', 'documentationUrl', 'publicSourceUrl', 'options'];
    }

    public function onFile(File $file): void
    {
        foreach ($this->fileNames() as $gtfsName => $fileName) {
            if ($file->fileName === $fileName) {
                $this->filesByGtfsName[$gtfsName] = $file;
            }
        }
    }

    public function onEnd(): void
    {
        $type = $this->cp->type;
        $files = $this->readerFiles($type);
        $builder = new GtfsGeoJsonBuilder(
            $this->cp->sourceUrl,
            $this->cp->bbox,
            $this->cp->departuresBaseUrl,
            $this->cp->sourceName ?? 'GTFS',
            $this->cp->documentationUrl,
            $this->cp->publicSourceUrl,
            $this->fallbackLineStringsFromStops()
        );

        $file = new File('gtfs-' . $type . '.geojson');
        $file->content = $builder->buildFromReader(new GtfsFileSetReader($files), $type);

        $this->pushFile($file);
    }

    /**
     * @return array<string, string>
     */
    private function fileNames(): array
    {
        return array_merge(self::DEFAULT_FILES, $this->cp->options['files'] ?? []);
    }

    /**
     * @return array<string, File>
     */
    private function readerFiles(string $type): array
    {
        if (!in_array($type, GtfsGeoJsonBuilder::SUPPORTED_TYPES, true)) {
            throw new RuntimeException('type must be one of: ' . implode(', ', GtfsGeoJsonBuilder::SUPPORTED_TYPES));
        }

        $required = ['routes', 'stops', 'stopTimes', 'trips'];
        $needsLines = $type === 'lines' || $type === 'combined';
        if ($needsLines && !$this->fallbackLineStringsFromStops()) {
            $required[] = 'shapes';
        }

        $files = [];
        $fileNames = $this->fileNames();
        foreach ($required as $gtfsName) {
            $file = $this->filesByGtfsName[$gtfsName] ?? null;
            if (!$file instanceof File) {
                throw new RuntimeException('Required GTFS file "' . $fileNames[$gtfsName] . '" is missing');
            }

            $files[self::DEFAULT_FILES[$gtfsName]] = $file;
        }
        if ($needsLines && isset($this->filesByGtfsName['shapes'])) {
            $files[self::DEFAULT_FILES['shapes']] = $this->filesByGtfsName['shapes'];
        }

        return $files;
    }

    private function fallbackLineStringsFromStops(): bool
    {
        return (bool)($this->cp->options['fallbackLineStringsFromStops'] ?? false);
    }
}

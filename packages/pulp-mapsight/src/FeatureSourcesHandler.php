<?php

declare(strict_types=1);

namespace OpenMapsight\pulpmapsight;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use RuntimeException;

class FeatureSourcesHandler extends AbstractHandler
{
    /** @var array<string, array{url: string, path: string, featureSourceIds: list<string>, titles: list<string>}> */
    private array $sources = [];

    protected function getConstructorParamDefs(): array
    {
        return ['origin', 'options'];
    }

    public function onFile(File $file): void
    {
        $origin = rtrim((string) $this->cp->origin, '/');
        $host = parse_url($origin, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new RuntimeException('Origin "' . $origin . '" has no host');
        }

        foreach (FeatureSources::fromHtml(
            (string) $file->content,
            $host,
            (string) ($this->cp->options['attribute'] ?? 'data-dehydrated-state'),
            $this->cp->options['types'] ?? ['xhr-json'],
        ) as $path => $source) {
            $known = $this->sources[$path] ?? ['url' => $origin . $path, 'path' => $path, 'featureSourceIds' => [], 'titles' => []];
            $known['featureSourceIds'] = array_values(array_unique([...$known['featureSourceIds'], ...$source['featureSourceIds']]));
            $known['titles'] = array_values(array_unique([...$known['titles'], ...$source['titles']]));
            $this->sources[$path] = $known;
        }
    }

    public function onEnd(): void
    {
        foreach ($this->sources as $path => $source) {
            $file = new File($path);
            $file->content = $source;
            $this->pushFile($file);
        }
    }
}

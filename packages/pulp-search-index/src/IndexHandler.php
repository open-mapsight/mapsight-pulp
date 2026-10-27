<?php

declare(strict_types=1);

namespace OpenMapsight\pulpsearchindex;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulp\Utils;
use RuntimeException;
use stdClass;
use Throwable;

class IndexHandler extends AbstractHandler
{
    /** @var array<string, array{titles: list<string>, rows: list<array>}> */
    private array $sources = [];

    protected function getConstructorParamDefs(): array
    {
        return ['fileName', 'options'];
    }

    public function onFile(File $file): void
    {
        try {
            $content = $file->content;
            $document = is_string($content) ? json_decode($content, false, 512, JSON_THROW_ON_ERROR) : $content;
            if (!is_array($document) && !$document instanceof stdClass) {
                throw new RuntimeException('Expected a GeoJSON FeatureCollection');
            }
            $titles = isset($this->cp->options['titles']) ? ($this->cp->options['titles'])($file) : ($file->titles ?? []);
            $this->sources[(string) $file->fileName] = [
                'titles' => array_values(array_filter(is_array($titles) ? $titles : [], 'is_string')),
                'rows' => SearchIndex::rows($document),
            ];
        } catch (Throwable $err) {
            $err = new RuntimeException('Indexing file "' . $file->fileName . '" failed', 0, $err);

            if ($this->cp->options['skipExceptions'] ?? false === true) {
                Utils::log($this->cp->options['logSkipExceptions'] ?? 'stderr', $err);

                return;
            }

            throw $err;
        }
    }

    public function onEnd(): void
    {
        if ($this->sources === []) {
            return;
        }
        $file = new File($this->cp->fileName);
        $file->content = [
            'version' => 1,
            'generatedAt' => (int) floor(microtime(true) * 1000),
            'sources' => $this->sources,
        ];
        $this->pushFile($file);
    }
}

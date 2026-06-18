<?php

declare(strict_types=1);

namespace OpenMapsight\pulpcache;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class CacheHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['cacheDirectory', 'options'];
    }

    public function onFile(File $file): void
    {
        $store = new CacheStore(
            (string)$this->cp->cacheDirectory,
            (string)($this->cp->options['key'] ?? $file->fileName)
        );
        $ttl = (int)($this->cp->options['ttl'] ?? 86400);

        if ($ttl !== 0 && $store->hasFresh($ttl)) {
            foreach ($store->read() as $cachedFile) {
                $this->pushFile($cachedFile);
            }

            return;
        }

        $store->write([$file]);
        $this->pushFile($file);
    }
}

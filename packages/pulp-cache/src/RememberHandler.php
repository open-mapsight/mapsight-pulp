<?php

declare(strict_types=1);

namespace OpenMapsight\pulpcache;

use OpenMapsight\Pulp;
use OpenMapsight\pulp\AbstractHandler;
use Throwable;

class RememberHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['source', 'cacheDirectory', 'options'];
    }

    public function onEnd(): void
    {
        $store = new CacheStore(
            (string)$this->cp->cacheDirectory,
            (string)($this->cp->options['key'] ?? 'remember')
        );
        $ttl = (int)($this->cp->options['ttl'] ?? 86400);

        if ($ttl !== 0 && $store->hasFresh($ttl)) {
            $this->pushCachedFiles($store);
            return;
        }

        try {
            /** @var Pulp $source */
            $source = $this->cp->source;
            $files = $source->run();
            $store->write($files);
        } catch (Throwable $throwable) {
            if (($this->cp->options['fallbackToStale'] ?? true) === true && $store->hasAny()) {
                $this->pushCachedFiles($store);
                return;
            }

            throw $throwable;
        }

        foreach ($files as $file) {
            $this->pushFile($file);
        }
    }

    private function pushCachedFiles(CacheStore $store): void
    {
        foreach ($store->read() as $file) {
            $this->pushFile($file);
        }
    }
}

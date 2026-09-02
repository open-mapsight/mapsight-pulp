<?php

declare(strict_types=1);

namespace OpenMapsight\pulpcache;

use OpenMapsight\Pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
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
            if ($this->shouldKeepCache($store, $files)) {
                $this->pushCachedFiles($store);

                return;
            }
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

    /**
     * @param File[] $files
     */
    private function shouldKeepCache(CacheStore $store, array $files): bool
    {
        if (!$store->hasAny()) {
            return false;
        }

        $options = $this->cp->options;
        if (($options['keepCacheWhenEmpty'] ?? false) === true && $files === []) {
            return true;
        }

        if (($options['keepCacheOnNotModified'] ?? false) !== true || $files === []) {
            return false;
        }

        foreach ($files as $file) {
            $status = (int) ($file->httpStatus ?? 0);
            if ($status !== 304 && $status !== 204) {
                return false;
            }
        }

        return true;
    }

    private function pushCachedFiles(CacheStore $store): void
    {
        foreach ($store->read() as $file) {
            $this->pushFile($file);
        }
    }
}

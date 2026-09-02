<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexfuel;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use RuntimeException;

class AccumulatePricesHandler extends AbstractHandler
{
    /** @var list<array{stationId: string, e5: float|null, e10: float|null, diesel: float|null, updatedAt: string}> */
    private array $prices = [];

    private string $packetType = 'DELTA';

    private string $lastModified = '';

    private bool $hasPacket = false;

    protected function getConstructorParamDefs(): array
    {
        return ['cachePath'];
    }

    public function onFile(File $file): void
    {
        $status = (int) ($file->httpStatus ?? 200);
        if ($status === 304 || $status === 204) {
            if (is_string($file->httpLastModified) && $file->httpLastModified !== '') {
                $this->lastModified = $file->httpLastModified;
            }

            return;
        }

        $this->hasPacket = true;
        $this->prices = array_merge(
            $this->prices,
            DatexFuelPrices::extractPrices(GeoJsonHandler::publicationFromFile($file))
        );
        if (is_string($file->httpType) && $file->httpType !== '') {
            $this->packetType = $file->httpType;
        }
        if (is_string($file->httpLastModified) && $file->httpLastModified !== '') {
            $this->lastModified = $file->httpLastModified;
        }
    }

    public function onEnd(): void
    {
        $cache = $this->readCache();
        if ($this->hasPacket) {
            $cache = DatexFuelPrices::applyPricePacketToCache(
                $cache,
                $this->prices,
                $this->packetType !== '' ? $this->packetType : 'DELTA',
                $this->lastModified
            );
            $this->writeCache($cache);
        } elseif ($this->lastModified !== '' && ($cache['lastModified'] ?? '') === '') {
            $cache['lastModified'] = $this->lastModified;
        }

        $file = new File(basename((string) $this->cp->cachePath) ?: 'datex-fuel-prices-cache.json');
        $file->content = $cache;
        $this->pushFile($file);
    }

    /**
     * @return array<string, mixed>
     */
    private function readCache(): array
    {
        $path = (string) $this->cp->cachePath;
        if ($path === '' || !is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $cache
     */
    private function writeCache(array $cache): void
    {
        $path = (string) $this->cp->cachePath;
        if ($path === '') {
            throw new RuntimeException('DATEX Fuel prices cache path must not be empty');
        }

        \OpenMapsight\Pulp::ensureDirectory(dirname($path));

        file_put_contents(
            $path,
            json_encode($cache, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }
}

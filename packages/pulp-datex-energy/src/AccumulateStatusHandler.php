<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexenergy;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use RuntimeException;

class AccumulateStatusHandler extends AbstractHandler
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    private bool $hasPacket = false;

    protected function getConstructorParamDefs(): array
    {
        return ['cachePath'];
    }

    public function onFile(File $file): void
    {
        $status = (int) ($file->httpStatus ?? 200);
        if ($status === 304 || $status === 204) {
            return;
        }
        if ($status !== 200) {
            throw new RuntimeException('DATEX Energy status packet returned HTTP ' . $status);
        }

        $publication = GeoJsonHandler::publicationFromFile($file);
        $validPublication = false;
        foreach (DatexEnergySitesBuilder::payloadItems($publication) as $payload) {
            if (is_array($payload['aegiEnergyInfrastructureStatusPublication'] ?? null)
                || isset($payload['energyInfrastructureSiteStatus'])) {
                $validPublication = true;
                break;
            }
        }
        if (!$validPublication) {
            throw new RuntimeException('DATEX Energy packet has no status publication');
        }
        $this->cache ??= $this->readCache();
        $lastModified = $file->mobilithekAdvanceCursor === false
            ? (string) ($this->cache['lastModified'] ?? '')
            : (string) ($file->httpLastModified ?? '');
        $this->cache = DatexEnergyStatus::applyPacketToCache(
            $this->cache,
            DatexEnergyStatus::extractPointStatuses($publication),
            DatexEnergyStatus::packetType($publication, (string) ($file->httpType ?? '')),
            $lastModified
        );
        $this->hasPacket = true;
    }

    public function onEnd(): void
    {
        $cache = $this->cache ?? $this->readCache();
        if ($this->hasPacket) {
            $cache['accumulatorVersion'] = 2;
            $this->writeCache($cache);
        }

        $file = new File(basename((string) $this->cp->cachePath) ?: 'datex-energy-status-cache.json');
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
            throw new RuntimeException('DATEX Energy status cache path must not be empty');
        }

        \OpenMapsight\Pulp::ensureDirectory(dirname($path));

        // A failed download/parse never commits a partial run. Rename keeps readers
        // from observing truncated JSON. Callers must serialize concurrent writers.
        $temporary = tempnam(dirname($path), '.datex-status-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to create DATEX Energy status cache');
        }
        try {
            $mode = is_file($path) ? (fileperms($path) & 0o777) : (0o666 & ~umask());
            if (!chmod($temporary, $mode)) {
                throw new RuntimeException('Unable to set DATEX Energy status cache permissions');
            }
            $json = json_encode($cache, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($temporary, $json) !== strlen($json) || !rename($temporary, $path)) {
                throw new RuntimeException('Unable to write DATEX Energy status cache');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}

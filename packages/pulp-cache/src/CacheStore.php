<?php

declare(strict_types=1);

namespace OpenMapsight\pulpcache;

use OpenMapsight\pulp\File;
use RuntimeException;

class CacheStore
{
    public function __construct(
        private readonly string $cacheDirectory,
        private readonly string $key,
    ) {
        if ($this->cacheDirectory === '') {
            throw new RuntimeException('Cache directory must not be empty');
        }
    }

    public function hasFresh(int $ttl): bool
    {
        $manifestPath = $this->manifestPath();

        return is_file($manifestPath) && ($ttl < 0 || filemtime($manifestPath) + $ttl >= time());
    }

    public function hasAny(): bool
    {
        return is_file($this->manifestPath());
    }

    /**
     * @return File[]
     */
    public function read(): array
    {
        $manifest = $this->readManifest();
        $files = [];

        foreach ($manifest['files'] ?? [] as $cacheFile) {
            $path = $this->cachePath((string)$cacheFile['path']);
            if (!is_file($path)) {
                throw new RuntimeException('Cache file is missing: ' . $path);
            }

            if (($cacheFile['encoding'] ?? 'raw') === 'raw') {
                $files[] = File::fromPath($path, (string)$cacheFile['fileName']);
                continue;
            }

            $file = new File((string)$cacheFile['fileName']);
            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException('Unable to read cache file: ' . $path);
            }

            $file->content = unserialize($content, ['allowed_classes' => true]);
            $files[] = $file;
        }

        return $files;
    }

    /**
     * @param File[] $files
     */
    public function write(array $files): void
    {
        $directory = $this->cachePath('');
        if (!is_dir($directory) && (!mkdir($directory, 0o777, true) && !is_dir($directory))) {
            throw new RuntimeException(sprintf('Cache directory "%s" was not created', $directory));
        }

        $manifest = [
            'createdAt' => time(),
            'files' => [],
        ];

        foreach (array_values($files) as $index => $file) {
            $fileName = $this->cacheFileName($index, $file);
            $path = $this->cachePath($fileName);
            $content = $file->content;
            $encoding = is_string($content) ? 'raw' : 'serialized';
            $data = $encoding === 'raw' ? $content : serialize($content);

            $tmpFile = tempnam($directory, '.cache-');
            if ($tmpFile === false || file_put_contents($tmpFile, $data) === false) {
                throw new RuntimeException('Unable to write cache file "' . $path . '"');
            }

            if (!rename($tmpFile, $path)) {
                @unlink($tmpFile);
                throw new RuntimeException('Unable to move cache file "' . $path . '" into place');
            }

            $manifest['files'][] = [
                'fileName' => $file->fileName,
                'path' => $fileName,
                'encoding' => $encoding,
            ];
        }

        $manifestContent = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $manifestTmpFile = tempnam($directory, '.manifest-');
        if ($manifestTmpFile === false || file_put_contents($manifestTmpFile, $manifestContent) === false) {
            throw new RuntimeException('Unable to write cache manifest');
        }

        if (!rename($manifestTmpFile, $this->manifestPath())) {
            @unlink($manifestTmpFile);
            throw new RuntimeException('Unable to move cache manifest into place');
        }
    }

    private function readManifest(): array
    {
        $content = file_get_contents($this->manifestPath());
        if ($content === false) {
            throw new RuntimeException('Unable to read cache manifest');
        }

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    private function manifestPath(): string
    {
        return $this->cachePath('manifest.json');
    }

    private function cachePath(string $path): string
    {
        return rtrim($this->cacheDirectory, '/') . '/' . $this->safeKey() . ($path !== '' ? '/' . $path : '');
    }

    private function safeKey(): string
    {
        return preg_replace('/[^a-zA-Z0-9_.-]+/', '-', $this->key) ?: 'default';
    }

    private function cacheFileName(int $index, File $file): string
    {
        $extension = pathinfo((string)$file->fileName, PATHINFO_EXTENSION);
        $fileName = str_pad((string)$index, 4, '0', STR_PAD_LEFT);

        return $extension !== '' ? $fileName . '.' . $extension : $fileName . '.cache';
    }
}

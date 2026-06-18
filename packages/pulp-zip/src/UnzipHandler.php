<?php

declare(strict_types=1);

namespace OpenMapsight\pulpzip;

use FilesystemIterator;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulp\Utils;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

class UnzipHandler extends AbstractHandler
{
    private bool $cleanupRegistered = false;

    /** @var string[] */
    private array $tmpDirs = [];

    protected function getConstructorParamDefs(): array
    {
        return ['patterns', 'options'];
    }

    public function onFile(File $file): void
    {
        $files = [];

        try {
            $files = $this->extractFiles($file);
        } catch (Throwable $err) {
            $err = new RuntimeException(
                'Unzipping file "' . $file->fileName . '" failed',
                0,
                $err
            );

            if ($this->cp->options['skipExceptions'] ?? false === true) {
                Utils::log($this->cp->options['logSkipExceptions'] ?? 'stderr', $err);

                return;
            }

            throw $err;
        }

        // Do not push inside the try block; downstream handler failures must not be swallowed here.
        foreach ($files as $unzippedFile) {
            $this->pushFile($unzippedFile);
        }
    }

    /**
     * @return File[]
     */
    private function extractFiles(File $file): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pulp-zip-');
        if ($tmpFile === false) {
            throw new RuntimeException('Unable to create temporary ZIP file');
        }

        $inputStream = $file->stream();
        $zipStream = fopen($tmpFile, 'wb');
        if ($zipStream === false) {
            fclose($inputStream);
            @unlink($tmpFile);
            throw new RuntimeException('Unable to open temporary ZIP file');
        }

        try {
            if (stream_copy_to_stream($inputStream, $zipStream) === false) {
                throw new RuntimeException('Unable to copy ZIP file into temporary file');
            }
        } finally {
            fclose($inputStream);
            fclose($zipStream);
        }

        $tmpDir = sys_get_temp_dir() . '/pulp-zip-' . bin2hex(random_bytes(8));
        if (!mkdir($tmpDir, 0o700) && !is_dir($tmpDir)) {
            @unlink($tmpFile);
            throw new RuntimeException('Unable to create temporary ZIP extract directory');
        }
        $this->tmpDirs[] = $tmpDir;
        $this->registerCleanup();

        $zip = new ZipArchive();
        $isOpen = false;
        try {
            if ($zip->open($tmpFile) !== true) {
                throw new RuntimeException('Unable to open ZIP file');
            }
            $isOpen = true;

            $files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stats = $zip->statIndex($i);
                $entryName = $stats['name'] ?? null;
                if ($entryName === null || str_ends_with($entryName, '/')) {
                    continue;
                }

                if (!$this->matchesPatterns($entryName)) {
                    continue;
                }

                $safeEntryName = self::validateEntryName($entryName);
                $destPath = $tmpDir . '/' . $safeEntryName;
                $destDir = dirname($destPath);
                if (!is_dir($destDir) && (!mkdir($destDir, 0o700, true) && !is_dir($destDir))) {
                    throw new RuntimeException('Unable to create ZIP entry directory "' . $destDir . '"');
                }

                $entryStream = $zip->getStream($entryName);
                if ($entryStream === false) {
                    throw new RuntimeException('Unable to open ZIP entry "' . $entryName . '"');
                }

                $destStream = fopen($destPath, 'wb');
                if ($destStream === false) {
                    fclose($entryStream);
                    throw new RuntimeException('Unable to write ZIP entry "' . $entryName . '"');
                }

                try {
                    if (stream_copy_to_stream($entryStream, $destStream) === false) {
                        throw new RuntimeException('Unable to extract ZIP entry "' . $entryName . '"');
                    }
                } finally {
                    fclose($entryStream);
                    fclose($destStream);
                }

                $unzippedFile = File::fromPath($destPath, $safeEntryName);
                $unzippedFile->srcFileName = $file->srcFileName . '#' . $safeEntryName;
                $unzippedFile->zipEntryStats = $stats;

                $files[] = $unzippedFile;
            }

            return $files;
        } finally {
            if ($isOpen) {
                $zip->close();
            }
            @unlink($tmpFile);
        }
    }

    private function matchesPatterns(string $fileName): bool
    {
        $patterns = $this->cp->patterns;
        if (!is_array($patterns)) {
            $patterns = [$patterns];
        }

        foreach ($patterns as $pattern) {
            if (Utils::matchFileName($pattern, $fileName)) {
                return true;
            }
        }

        return false;
    }

    public static function validateEntryName(string $entryName): string
    {
        $entryName = str_replace('\\', '/', $entryName);

        if ($entryName === '' || str_starts_with($entryName, '/')) {
            throw new RuntimeException('Unsafe ZIP entry name "' . $entryName . '"');
        }

        foreach (explode('/', $entryName) as $segment) {
            if ($segment === '' || $segment === '..') {
                throw new RuntimeException('Unsafe ZIP entry name "' . $entryName . '"');
            }
        }

        return $entryName;
    }

    public function cleanup(): void
    {
        foreach ($this->tmpDirs as $tmpDir) {
            $this->removeDirectory($tmpDir);
        }

        $this->tmpDirs = [];
    }

    private function registerCleanup(): void
    {
        if ($this->cleanupRegistered) {
            return;
        }

        register_shutdown_function([$this, 'cleanup']);
        $this->cleanupRegistered = true;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($directory);
    }
}

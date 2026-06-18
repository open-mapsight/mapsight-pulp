<?php

declare(strict_types=1);

namespace OpenMapsight\pulpzip;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulp\Utils;
use RuntimeException;
use Throwable;
use ZipArchive;

class ZipHandler extends AbstractHandler
{
    /** @var File[] */
    private array $files = [];

    protected function getConstructorParamDefs(): array
    {
        return ['fileName', 'options'];
    }

    public function onFile(File $file): void
    {
        $this->files[] = $file;
    }

    public function onEnd(): void
    {
        try {
            $file = $this->createZipFile();
        } catch (Throwable $err) {
            $err = new RuntimeException(
                'Creating ZIP file "' . $this->cp->fileName . '" failed',
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
        $this->pushFile($file);
    }

    private function createZipFile(): File
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pulp-zip-');
        if ($tmpFile === false) {
            throw new RuntimeException('Unable to create temporary ZIP file');
        }

        $zip = new ZipArchive();
        $isOpen = false;
        try {
            if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to open temporary ZIP file');
            }
            $isOpen = true;

            foreach ($this->files as $file) {
                if (!is_string($file->content)) {
                    throw new RuntimeException('ZIP entry "' . $file->fileName . '" content must be a string');
                }

                $entryName = UnzipHandler::validateEntryName((string)$file->fileName);
                if ($zip->addFromString($entryName, $file->content) === false) {
                    throw new RuntimeException('Unable to add ZIP entry "' . $entryName . '"');
                }
            }

            $closeResult = $zip->close();
            $isOpen = false;
            if ($closeResult === false) {
                throw new RuntimeException('Unable to close ZIP file');
            }

            $content = file_get_contents($tmpFile);
            if ($content === false) {
                throw new RuntimeException('Unable to read temporary ZIP file');
            }

            $zipFile = new File($this->cp->fileName);
            $zipFile->content = $content;

            return $zipFile;
        } finally {
            if ($isOpen) {
                $zip->close();
            }
            @unlink($tmpFile);
        }
    }
}

<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use RuntimeException;
use Throwable;

class DestHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['directory', 'options'];
    }

    public function onFile(File $file): void
    {
        try {
            $this->writeFile($file);
        } catch (Throwable $err) {
            $err = new RuntimeException(
                'Writing file "' . $file->fileName . '" failed',
                0,
                $err
            );

            if ($this->cp->options['skipExceptions'] ?? false === true) {
                Utils::log($this->cp->options['logSkipExceptions'] ?? 'stderr', $err);
            } else {
                throw $err;
            }
        }

        $this->pushFile($file);
    }

    private function writeFile(File $file): void
    {
        $dest = $this->cp->directory . '/' . $file->fileName;
        $path = dirname($dest);
        /** @noinspection NestedPositiveIfStatementsInspection */
        // pattern for race condition free mkdir
        if (!is_dir($path) && (!mkdir($path, 0o777, true) && !is_dir($path))) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        // we need to open the file to lock it.
        // using "c" instead of "w", "w" would truncate the file
        $h = fopen($dest, 'cb');
        if ($h === false) {
            throw new RuntimeException('Unable to open file "' . $dest . '"');
        }

        try {
            if (flock($h, LOCK_EX) === false) {
                throw new RuntimeException('Unable to lock file "' . $dest . '"');
            }

            try {
                $writeFile = true;

                if (
                    isset(
                        $this->cp->options['handleLogicallyEmpty'],
                        $file->isLogicallyEmpty
                    ) &&
                    $this->cp->options['handleLogicallyEmpty'] === true &&
                    $file->isLogicallyEmpty === true
                ) {
                    $emptyContent = $this->cp->options['logicallyEmptyContent'] ?? '';

                    $file->content = $emptyContent;

                    if (@filesize($dest) === strlen($emptyContent)) {
                        $writeFile = false;
                    }
                }

                if ($writeFile) {
                    if (ftruncate($h, 0) === false) {
                        throw new RuntimeException('Unable to truncate file "' . $dest . '"');
                    }

                    if (fwrite($h, $file->content) === false) {
                        throw new RuntimeException('Unable to write to file "' . $dest . '"');
                    }
                }

                if (
                    isset($this->cp->options['flush'])
                    && $this->cp->options['flush'] === true
                    && fflush($h) === false
                ) {
                    throw new RuntimeException('Unable to flush file "' . $dest . '"');
                }
            } finally {
                if (flock($h, LOCK_UN) === false) {
                    throw new RuntimeException('Unable to unlock file "' . $dest . '"');
                }
            }
        } finally {
            if (fclose($h) === false) {
                throw new RuntimeException('Unable to close file "' . $dest . '"');
            }
        }
    }
}

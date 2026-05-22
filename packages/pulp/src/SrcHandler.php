<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SrcHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return [
            'patterns',
            [
                'key' => 'directory',
                'default' => '.',
            ],
        ];
    }

    public function onEnd(): void
    {
        $patterns = $this->cp->patterns;

        if (!is_array($patterns)) {
            $patterns = [$patterns];
        }

        $directory = $this->cp->directory;
        $directory = ltrim(rtrim((string) $directory, '/')) . '/';
        $directoryLen = strlen($directory);

        foreach ($patterns as $pattern) {
            if ($pattern instanceof File) {
                $this->pushFile($pattern);
                continue;
            }

            if (is_file($pattern) && is_readable($pattern)) {
                $this->pushFile(File::fromPath($pattern));
                continue;
            }

            $dirIt = new RecursiveDirectoryIterator(
                $this->cp->directory,
                FilesystemIterator::SKIP_DOTS
                | FilesystemIterator::UNIX_PATHS
            );
            $itIt = new RecursiveIteratorIterator($dirIt);

            foreach ($itIt as $f) {
                $realPath = $f->getPathname();
                $path = substr((string) $realPath, $directoryLen);

                if (Utils::matchFileName($pattern, $path)) {
                    $this->pushFile(File::fromPath($realPath, $path));
                }
            }
        }
    }
}

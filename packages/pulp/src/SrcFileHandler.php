<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use RuntimeException;
use Throwable;

class SrcFileHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['fileName', 'aliasFileName', 'options'];
    }

    public function onEnd(): void
    {
        $file = null;
        try {
            $file = File::fromPath(
                $this->cp->fileName,
                $this->cp->aliasFileName
            );
        } catch (Throwable $err) {
            $err = new RuntimeException(
                'Reading file "' . $this->cp->fileName . '" failed',
                0,
                $err
            );

            if ($this->cp->options['skipExceptions'] ?? false === true) {
                Utils::log($this->cp->options['logSkipExceptions'] ?? 'stderr', $err);
            } else {
                throw $err;
            }
        }

        if ($file instanceof File) {
            // not in the try block to not catch exceptions from other handlers
            $this->pushFile($file);
        }
    }
}

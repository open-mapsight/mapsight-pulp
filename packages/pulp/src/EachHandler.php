<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use OpenMapsight\Pulp;
use RuntimeException;
use Throwable;

class EachHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['cb', 'options'];
    }

    public function onFile(File $file): void
    {
        try {
            $pipeline = $this->cp->cb($file);
            if (!$pipeline instanceof Pulp) {
                throw new RuntimeException('Each callback must return a Pulp instance');
            }

            $files = $pipeline->run();
        } catch (Throwable $err) {
            $err = new RuntimeException('Pipeline for file "' . $file->fileName . '" failed', 0, $err);

            if ($this->cp->options['skipExceptions'] ?? false === true) {
                Utils::log($this->cp->options['logSkipExceptions'] ?? 'stderr', $err);

                return;
            }

            throw $err;
        }

        // not in the try block to not catch exceptions from other handlers
        foreach ($files as $result) {
            $this->pushFile($result);
        }
    }
}

<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use OpenMapsight\Pulp;

class ShadowHandler extends AbstractHandler
{
    private Pulp $pulp;

    protected function getConstructorParamDefs(): array
    {
        return ['cb'];
    }

    public function onStart(): void
    {
        $this->pulp = $this->cp->cb(Pulp::start());
    }

    public function onFile(File $file): void
    {
        if (($firstHandler = $this->pulp->getFirstHandler()) instanceof Handler) {
            $firstHandler->handleFile(clone $file);
        }

        $this->pushFile($file);
    }

    public function onEnd(): void
    {
        if (($firstHandler = $this->pulp->getFirstHandler()) instanceof Handler) {
            $firstHandler->handleFile();
        }
    }
}

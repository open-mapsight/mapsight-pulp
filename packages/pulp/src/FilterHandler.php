<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

class FilterHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['cb'];
    }

    public function onFile(File $file): void
    {
        $returnValue = $this->cp->cb($file);
        if ($returnValue === true) {
            $this->pushFile($file);
        }
    }
}

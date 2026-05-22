<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

class MapHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['cb'];
    }

    public function onFile(File $file): void
    {
        $file = $this->cp->cb($file);
        if ($file !== null) {
            $this->pushFile($file);
        }
    }
}

<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

class MergeHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['pulps'];
    }

    public function onEnd(): void
    {
        foreach ($this->cp->pulps as $p) {
            foreach ($p->run() as $r) {
                $this->pushFile($r);
            }
        }
    }
}

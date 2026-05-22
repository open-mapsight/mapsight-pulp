<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

class ResultsHandler extends AbstractHandler
{
    private array $results = [];

    protected function getConstructorParamDefs(): array
    {
        return ['cb'];
    }

    public function onFile(File $file): void
    {
        $this->results[] = clone $file;
        $this->pushFile($file);
    }

    public function onEnd(): void
    {
        $this->cp->cb($this->results);
    }
}

<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use OpenMapsight\Pulp;

abstract class AbstractHandler implements Handler
{
    /** @var Parameters constructor parameters */
    protected Parameters $cp;

    private bool $isFirstFile = true;
    private ?Handler $nextHandler = null;
    private ?Pulp $pulp = null;

    public function __construct()
    {
        $this->cp = new Parameters($this->getConstructorParamDefs());
        $this->cp->bindParameters(func_get_args());
    }

    protected function getConstructorParamDefs(): array
    {
        return [];
    }

    public function handleFile(?File $file = null): void
    {
        if ($this->isFirstFile) {
            $this->onStart();
            $this->isFirstFile = false;
        }

        if ($file instanceof File) {
            $this->onFile($file);
        } else {
            $this->onEnd();

            if ($this->nextHandler instanceof Handler) {
                $this->nextHandler->handleFile();
            }
        }
    }

    public function onStart() {}

    public function onFile(File $file): void
    {
        $this->pushFile($file);
    }

    public function pushFile(File $file): void
    {
        if ($this->nextHandler instanceof Handler) {
            $this->nextHandler->handleFile($file);
        } elseif ($this->pulp instanceof Pulp) {
            $this->pulp->addResultFile($file);
        }
    }

    public function onEnd() {}

    public function setNextHandler(Handler $handler): void
    {
        $this->nextHandler = $handler;
    }

    public function setPulp(Pulp $pulp): void
    {
        $this->pulp = $pulp;
    }
}

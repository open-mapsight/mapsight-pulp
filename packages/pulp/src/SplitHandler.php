<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use OpenMapsight\Pulp;
use RuntimeException;

class SplitHandler extends AbstractHandler
{
    /** @var Pulp[] */
    private array $branches = [];

    protected function getConstructorParamDefs(): array
    {
        return ['pipelines'];
    }

    public function onStart(): void
    {
        foreach ($this->cp->pipelines as $pipeline) {
            if (is_callable($pipeline)) {
                $pipeline = $pipeline(Pulp::start());
            }

            if (!$pipeline instanceof Pulp) {
                throw new RuntimeException('Split pipeline must be a Pulp instance or callable returning Pulp');
            }

            $this->branches[] = $pipeline;
        }
    }

    public function onFile(File $file): void
    {
        foreach ($this->branches as $branch) {
            if (($firstHandler = $branch->getFirstHandler()) instanceof Handler) {
                $firstHandler->handleFile(clone $file);
            } else {
                $branch->addResultFile(clone $file);
            }
        }
    }

    public function onEnd(): void
    {
        foreach ($this->branches as $branch) {
            if (($firstHandler = $branch->getFirstHandler()) instanceof Handler) {
                $firstHandler->handleFile();
            }

            foreach ($branch->getResultFiles() as $file) {
                $this->pushFile($file);
            }
        }
    }
}

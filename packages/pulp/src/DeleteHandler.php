<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

class DeleteHandler extends AbstractHandler
{
    public function onFile(File $file): void
    {
        unlink($file->srcFileName);
        $this->pushFile($file);
    }
}

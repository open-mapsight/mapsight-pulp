<?php

declare(strict_types=1);

namespace OpenMapsight\pulpogc;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class SrcWfsHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['client', 'typeName', 'fileName', 'request'];
    }

    public function onEnd(): void
    {
        $collection = $this->cp->client->getFeature($this->cp->typeName, $this->cp->request);
        $file = new File($this->cp->fileName);
        $file->content = $collection;
        $this->pushFile($file);
    }
}

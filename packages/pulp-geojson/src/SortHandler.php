<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class SortHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['cb'];
    }

    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertFile($file);

        /** @var array $data */
        $data = $file->content;
        usort($data['features'], $this->cp->cb);
        $file->content = $data;
        $this->pushFile($file);
    }
}

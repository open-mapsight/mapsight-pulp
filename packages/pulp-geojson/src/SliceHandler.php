<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class SliceHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return [
            [
                'key' => 'start',
                'handler' => fn($value): int => is_int($value) ? $value : 0,
            ],
            [
                'key' => 'length',
                'handler' => fn($value): ?int => is_int($value) ? $value : null,
            ],
        ];
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

        $data['features'] = array_slice(
            $data['features'],
            $this->cp->start,
            $this->cp->length
        );

        $file->content = $data;
        $this->pushFile($file);
    }
}

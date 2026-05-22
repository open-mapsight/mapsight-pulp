<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

class Utf8DecodeHandler extends AbstractHandler
{
    public function onFile(File $file): void
    {
        $file->content = mb_convert_encoding($file->content, 'ISO-8859-1');
        $this->pushFile($file);
    }
}

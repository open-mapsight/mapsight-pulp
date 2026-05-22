<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

class ServeHttpHandler extends AbstractHandler
{
    public function onFile(File $file): never
    {
        header('Content-Disposition: inline; filename="' . basename($file->fileName) . '"');
        header('Content-Length: ' . mb_strlen($file->content, '8bit'));
        ob_clean();
        flush();
        echo $file->content;
        exit;
    }
}

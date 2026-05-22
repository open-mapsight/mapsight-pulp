<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

class DebugHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return [
            ['key' => 'length', 'default' => 30],
        ];
    }

    public function onFile(File $file): void
    {
        echo '"', $file->fileName, '"';

        $len = $this->cp->length;
        if (0 < $len) {
            $content = $file->content;

            if (!is_string($content)) {
                $content = print_r($content, true);
            }

            if ($len < strlen($content)) {
                $content = substr($content, 0, $len) . '[...]';
            }
            echo ':(', getType($file->content), ') ', $content;
        }

        echo "\n";

        $this->pushFile($file);
    }
}

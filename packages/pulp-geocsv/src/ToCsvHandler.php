<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeocsv;

use OpenMapsight\Pulp\AbstractHandler;
use OpenMapsight\Pulp\File;
use RuntimeException;

class ToCsvHandler extends AbstractHandler
{
    public function onFile(File $file): void
    {
        $csv = $file->content;
        if (!is_array($csv) || $csv['type'] !== 'text/csv') {
            throw new RuntimeException('File is not csv!');
        }

        $ls = $this->cp->lineSeparator;
        $fs = $this->cp->fieldSeparator;
        $qc = $this->cp->quoteChar;

        $rows = $csv['rows'];
        $columns = $csv['columns'];

        $result = self::makeCsvString($columns, $fs, $qc) . $ls;
        foreach ($rows as $row) {
            $result .= self::makeCsvString($row, $fs, $qc) . $ls;
        }

        $file->content = $result;
        $this->pushFile($file);
    }

    protected static function makeCsvString(array $fields, string $delimiter, string $enclosure): string
    {
        $f = fopen('php://memory', 'rwb');
        if (fputcsv($f, $fields, $delimiter, $enclosure, '') === false) {
            return false;
        }
        rewind($f);
        $csv_line = stream_get_contents($f);
        return rtrim($csv_line);
    }

    protected function getConstructorParamDefs(): array
    {
        return ['fieldSeparator', 'lineSeparator', 'quoteChar'];
    }
}

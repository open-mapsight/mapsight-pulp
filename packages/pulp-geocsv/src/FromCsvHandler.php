<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeocsv;

use OpenMapsight\Pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use RuntimeException;

class FromCsvHandler extends AbstractHandler
{
    public function onFile(File $file): void
    {
        $csv = $file->content;
        if (!is_string($csv)) {
            throw new RuntimeException('File content is not string!');
        }

        $ls = $this->cp->lineSeparator;
        $fs = $this->cp->fieldSeparator;
        $qc = $this->cp->quoteChar;

        $rows = explode($ls, $csv);
        $rows = array_map(static function ($row) use ($fs, $qc): array {
            $items = str_getcsv($row, $fs, $qc);
            return array_map(stripslashes(...), $items);
        }, $rows);

        $firstRow = array_shift($rows);

        $columnCounter = 0;
        $columns = array_map(static function ($columnName) use ($columnCounter): string {
            $columnCounter++;
            return $columnName === '' || $columnName === '0' ? '_' . $columnCounter : $columnName;
        }, $firstRow);

        $file->content = [
            'type' => 'text/csv',
            'sourceFormat' => [
                'columnNamesInFirstRow' => true,
                'lineSeparator' => $ls,
                'fieldSeparator' => $fs,
                'quoteChar' => $qc,
            ],
            'columns' => $columns,
            'rows' => $rows,
        ];
        $this->pushFile($file);
    }

    protected function getConstructorParamDefs(): array
    {
        return ['fieldSeparator', 'lineSeparator', 'quoteChar'];
    }
}

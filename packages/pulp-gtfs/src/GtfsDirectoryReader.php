<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgtfs;

use Generator;
use RuntimeException;

class GtfsDirectoryReader implements GtfsReader
{
    public function __construct(private readonly string $directory)
    {
    }

    public function csvRows(string $fileName): Generator
    {
        $path = $this->directory . '/' . $this->validateFileName($fileName);
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException(sprintf('GTFS file "%s" is missing', $fileName));
        }

        try {
            $headers = fgetcsv($stream);
            if ($headers === false) {
                return;
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headers[0]);
            $headerCount = count($headers);

            while (($row = fgetcsv($stream)) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }

                if (count($row) < $headerCount) {
                    $row = array_pad($row, $headerCount, '');
                } elseif (count($row) > $headerCount) {
                    $row = array_slice($row, 0, $headerCount);
                }

                yield array_combine($headers, $row);
            }
        } finally {
            fclose($stream);
        }
    }

    private function validateFileName(string $fileName): string
    {
        if ($fileName === '' || str_contains($fileName, '/') || str_contains($fileName, '\\')) {
            throw new RuntimeException('Invalid GTFS file name "' . $fileName . '"');
        }

        return $fileName;
    }
}

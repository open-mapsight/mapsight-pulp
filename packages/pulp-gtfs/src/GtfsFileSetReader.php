<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgtfs;

use Generator;
use OpenMapsight\pulp\File;
use RuntimeException;

class GtfsFileSetReader implements GtfsReader
{
    /**
     * @param array<string, File> $filesByName
     */
    public function __construct(private readonly array $filesByName)
    {
    }

    public function csvRows(string $fileName): Generator
    {
        $file = $this->filesByName[$fileName] ?? null;
        if (!$file instanceof File) {
            throw new RuntimeException(sprintf('GTFS file "%s" is missing', $fileName));
        }

        $stream = $file->stream();

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
}

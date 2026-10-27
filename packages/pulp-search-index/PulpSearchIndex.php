<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulp\File;
use OpenMapsight\pulppdo\TransactionHandler;
use OpenMapsight\pulpsearchindex\IndexHandler;
use OpenMapsight\pulpsearchindex\SearchIndexDatabase;
use PDO;
use RuntimeException;

class PulpSearchIndex
{
    /**
     * Replaces the incoming GeoJSON files with one search index:
     * `['version' => 1, 'generatedAt' => ms, 'sources' => [fileName => ['titles' => […], 'rows' => […]]]`.
     * Each file is one source, keyed by its file name; its content may be a
     * JSON string or a decoded FeatureCollection. A source's `titles` (for
     * example the titles of the map layers showing it) let a query word
     * match every place of the source. Without any source, nothing is
     * emitted.
     *
     * ## Options
     * * `titles`: `callable(File): list<string>` (default: the file's `titles` property)
     * * `skipExceptions`: log a file that is no FeatureCollection and leave it out (default: `false`)
     * * `logSkipExceptions`: `"stderr"` (default), `"stdout"` or `false`
     *
     * @param array<string, mixed> $options
     */
    public static function index(string $fileName = 'search-index.json', array $options = []): IndexHandler
    {
        return new IndexHandler($fileName, $options);
    }

    /**
     * Stores each index file in the tables `<prefix>_sources` and
     * `<prefix>_rows` in one transaction, creating them first if needed.
     * Search them with `SearchIndexDatabase::search()`.
     *
     * ## Options
     * * `prefix`: table name prefix (default: `"search_index"`)
     * * `skipExceptions`: log a failing write, roll it back, and continue (default: `false`)
     * * `logSkipExceptions`: `"stderr"` (default), `"stdout"` or `false`
     *
     * @param PDO|callable(): PDO $connection A connection, or a callback opening one on first use
     * @param array<string, mixed> $options
     */
    public static function toDatabase(PDO|callable $connection, array $options = []): TransactionHandler
    {
        $prefix = (string) ($options['prefix'] ?? SearchIndexDatabase::DEFAULT_PREFIX);

        return PulpPdo::transaction(
            $connection,
            static function (PDO $pdo, File $file) use ($prefix): void {
                if (!is_array($file->content) || !is_array($file->content['sources'] ?? null)) {
                    throw new RuntimeException('Expected a search index');
                }
                SearchIndexDatabase::write($pdo, $file->content, $prefix);
            },
            [
                'setUp' => static fn(PDO $pdo) => SearchIndexDatabase::createTables($pdo, $prefix),
                'skipExceptions' => $options['skipExceptions'] ?? false,
                'logSkipExceptions' => $options['logSkipExceptions'] ?? 'stderr',
            ],
        );
    }
}

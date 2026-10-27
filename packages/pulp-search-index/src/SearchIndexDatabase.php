<?php

declare(strict_types=1);

namespace OpenMapsight\pulpsearchindex;

use InvalidArgumentException;
use PDO;

/**
 * A search index in two tables, `<prefix>_sources` and `<prefix>_rows`,
 * for MySQL/MariaDB and SQLite.
 *
 * `search()` narrows the rows in SQL and ranks them with
 * `SearchIndex::search()`, so stored and in-memory indexes return the same
 * places.
 */
final class SearchIndexDatabase
{
    public const DEFAULT_PREFIX = 'search_index';

    /** Creates the tables if they do not exist. Run it outside a transaction: MySQL commits one on DDL. */
    public static function createTables(PDO $pdo, string $prefix = self::DEFAULT_PREFIX): void
    {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $long = $mysql ? 'MEDIUMTEXT' : 'TEXT';
        $table = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin' : '';
        [$sources, $rows] = self::tables($pdo, $prefix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $sources . ' (
            path VARCHAR(512) NOT NULL PRIMARY KEY,
            titles TEXT NOT NULL,
            digest CHAR(40) NOT NULL,
            updated_at BIGINT NOT NULL,
            indexed_at BIGINT NOT NULL
        )' . $table);
        $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $rows . ' (
            path VARCHAR(512) NOT NULL,
            position INT NOT NULL,
            id TEXT NOT NULL,
            title TEXT NOT NULL,
            subtitle TEXT NOT NULL,
            words ' . $long . ' NOT NULL,
            numbers TEXT NOT NULL,
            point TEXT NULL,
            PRIMARY KEY (path, position)
        )' . $table);
    }

    /**
     * Stores an index (`['sources' => [key => ['titles' => […], 'rows' => […]]]`):
     * replaces the rows of changed sources, removes sources that are gone,
     * and keeps `updated_at` of sources whose rows did not change. Run it in
     * a transaction so readers never see half an index.
     *
     * @param array{sources: array<string, array{titles?: list<string>, rows: list<array>}>} $index
     */
    public static function write(PDO $pdo, array $index, string $prefix = self::DEFAULT_PREFIX, ?int $now = null): void
    {
        $now ??= (int) floor(microtime(true) * 1000);
        [$sourcesTable, $rowsTable] = self::tables($pdo, $prefix);
        $stored = [];
        foreach ($pdo->query('SELECT path, digest, updated_at FROM ' . $sourcesTable)->fetchAll(PDO::FETCH_ASSOC) as $source) {
            $stored[(string) $source['path']] = $source;
        }

        $deleteRows = $pdo->prepare('DELETE FROM ' . $rowsTable . ' WHERE path = ?');
        foreach (array_diff_key($stored, $index['sources']) as $path => $source) {
            $deleteRows->execute([(string) $path]);
        }
        $pdo->exec('DELETE FROM ' . $sourcesTable);

        $insertSource = $pdo->prepare('INSERT INTO ' . $sourcesTable
            . ' (path, titles, digest, updated_at, indexed_at) VALUES (?, ?, ?, ?, ?)');
        foreach ($index['sources'] as $path => $source) {
            $path = (string) $path;
            $digest = sha1(self::json($source['rows']));
            $unchanged = isset($stored[$path]) && $stored[$path]['digest'] === $digest;
            if (!$unchanged) {
                $deleteRows->execute([$path]);
                self::insertRows($pdo, $rowsTable, $path, $source['rows']);
            }
            $insertSource->execute([$path, self::json(array_values($source['titles'] ?? [])), $digest,
                $unchanged ? (int) $stored[$path]['updated_at'] : $now, $now]);
        }
    }

    /**
     * Places for a query, as `SearchIndex::search()` returns them. Without
     * `$requested`, every stored source is searched with equal priority.
     *
     * @param list<array{0: int, 1: string}>|null $requested [priority, key], lower priority first
     * @return array{places: list<array>, missing: list<string>}
     */
    public static function search(PDO $pdo, ?array $requested, string $query, int $limit = 40, string $prefix = self::DEFAULT_PREFIX): array
    {
        [$sourcesTable, $rowsTable] = self::tables($pdo, $prefix);
        $titles = self::titles($pdo, $requested === null ? null : array_column($requested, 1), $prefix);
        $requested ??= array_map(static fn(string $path): array => [0, $path], array_keys($titles));
        $sources = array_map(static fn(array $titles): array => ['titles' => $titles, 'rows' => []], $titles);
        $tokens = SearchIndex::tokens($query);
        if ($tokens === [] || $sources === []) {
            return SearchIndex::search($sources, $requested, $query, $limit);
        }

        // A superset of the matching rows; SearchIndex::search() decides.
        $conditions = ['path IN (' . self::placeholders(count($sources)) . ')'];
        $params = array_map('strval', array_keys($sources));
        foreach ($tokens as $token) {
            $any = ['words LIKE ? ESCAPE \'!\''];
            $params[] = '%' . self::like($token) . '%';
            if (ctype_digit($token)) {
                $any[] = 'numbers LIKE ? ESCAPE \'!\'';
                $params[] = '%' . self::like(self::json(SearchIndex::numberKey($token))) . '%';
            } else {
                $byTitle = array_keys(array_filter(
                    $titles,
                    static fn(array $list): bool => str_contains(SearchIndex::normalize(implode(' ', $list)), $token),
                ));
                if ($byTitle !== []) {
                    $any[] = 'path IN (' . self::placeholders(count($byTitle)) . ')';
                    array_push($params, ...array_map('strval', $byTitle));
                }
            }
            $conditions[] = '(' . implode(' OR ', $any) . ')';
        }
        $statement = $pdo->prepare('SELECT path, id, title, subtitle, words, numbers, point FROM ' . $rowsTable
            . ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY path, position');
        $statement->execute($params);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sources[(string) $row['path']]['rows'][] = [
                (string) $row['id'], (string) $row['title'], (string) $row['subtitle'], (string) $row['words'],
                json_decode((string) $row['numbers'], true), $row['point'] === null ? null : json_decode((string) $row['point'], true),
            ];
        }

        return SearchIndex::search($sources, $requested, $query, $limit);
    }

    /**
     * Stored sources with their titles; only `$paths` when given.
     *
     * @param list<string>|null $paths
     * @return array<string, list<string>>
     */
    public static function titles(PDO $pdo, ?array $paths = null, string $prefix = self::DEFAULT_PREFIX): array
    {
        [$sourcesTable] = self::tables($pdo, $prefix);
        $paths = $paths === null ? null : array_values(array_unique(array_map('strval', $paths)));
        if ($paths === []) {
            return [];
        }
        $statement = $pdo->prepare('SELECT path, titles FROM ' . $sourcesTable
            . ($paths === null ? '' : ' WHERE path IN (' . self::placeholders(count($paths)) . ')') . ' ORDER BY path');
        $statement->execute($paths ?? []);
        $titles = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $source) {
            $titles[(string) $source['path']] = json_decode((string) $source['titles'], true);
        }

        return $titles;
    }

    /**
     * @param list<array> $rows
     */
    private static function insertRows(PDO $pdo, string $rowsTable, string $path, array $rows): void
    {
        foreach (array_chunk($rows, 100, true) as $chunk) {
            $params = [];
            foreach ($chunk as $position => [$id, $title, $subtitle, $words, $numbers, $point]) {
                array_push($params, $path, $position, $id, $title, $subtitle, $words, self::json($numbers),
                    $point === null ? null : self::json($point));
            }
            $pdo->prepare('INSERT INTO ' . $rowsTable . ' (path, position, id, title, subtitle, words, numbers, point) VALUES '
                . implode(', ', array_fill(0, count($chunk), '(' . self::placeholders(8) . ')')))->execute($params);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function tables(PDO $pdo, string $prefix): array
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Invalid table prefix "' . $prefix . '"');
        }
        $quote = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? '`' : '"';

        return [$quote . $prefix . '_sources' . $quote, $quote . $prefix . '_rows' . $quote];
    }

    private static function placeholders(int $count): string
    {
        return implode(', ', array_fill(0, $count, '?'));
    }

    private static function like(string $value): string
    {
        return strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }

    /** Shortest round-trip floats, so coordinates come back unchanged. */
    private static function json(mixed $value): string
    {
        $precision = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } finally {
            ini_set('serialize_precision', (string) $precision);
        }
    }
}

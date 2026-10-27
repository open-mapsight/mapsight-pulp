<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulp\File;
use OpenMapsight\pulppdo\QueryHandler;
use OpenMapsight\pulppdo\TransactionHandler;
use OpenMapsight\pulppdo\WriteHandler;
use PDO;

class PulpPdo
{
    /**
     * Emits one file whose content is the rows of `$sql`, as a list of
     * associative arrays. Incoming files pass through.
     *
     * @param PDO|callable(): PDO $connection A connection, or a callback opening one on first use
     * @param array<int|string, mixed> $params
     */
    public static function query(
        PDO|callable $connection,
        string $sql,
        array $params = [],
        string $fileName = 'query.json',
    ): QueryHandler {
        return new QueryHandler($connection, $sql, $params, $fileName);
    }

    /**
     * Inserts each file's rows into the existing `$table`, one transaction
     * per file. It does not create or change the table (see `setUp`).
     *
     * Each file's content must be a list of rows, each an array of column
     * names to values. Every row of a file needs the same columns, in any
     * order. Values may be strings, numbers, null, booleans (stored as 0/1)
     * or arrays (stored as JSON). Content that does not fit fails the file.
     *
     * ## Options
     * * `replace`: delete rows before inserting, in the same transaction (default: `false`)
     *   * `true`: every row of the table
     *   * `callable(File): array<string, scalar|null>`: the rows matching these column values
     * * `chunkSize`: rows per `INSERT` statement (default: `100`)
     * * `setUp`: `callable(PDO): void` run once before the first write, outside any transaction, e.g. to create the table
     * * `skipExceptions`: log a failing file, roll it back, and continue (default: `false`)
     * * `logSkipExceptions`: `"stderr"` (default), `"stdout"` or `false`
     *
     * @param PDO|callable(): PDO $connection A connection, or a callback opening one on first use
     * @param string|callable(File): string $table
     * @param array<string, mixed> $options
     */
    public static function write(PDO|callable $connection, string|callable $table, array $options = []): WriteHandler
    {
        return new WriteHandler($connection, $table, $options);
    }

    /**
     * Calls `$write(PDO $pdo, File $file)` for each file inside a
     * transaction: committed when it returns, rolled back when it throws.
     * For writes that `write()` does not cover, such as several tables or
     * rows that depend on what is stored.
     *
     * ## Options
     * * `setUp`: `callable(PDO): void` run once before the first write, outside any transaction, e.g. to create tables
     * * `skipExceptions`: log a failing file and continue (default: `false`)
     * * `logSkipExceptions`: `"stderr"` (default), `"stdout"` or `false`
     *
     * @param PDO|callable(): PDO $connection A connection, or a callback opening one on first use
     * @param callable(PDO, File): void $write
     * @param array<string, mixed> $options
     */
    public static function transaction(PDO|callable $connection, callable $write, array $options = []): TransactionHandler
    {
        return new TransactionHandler($connection, $write, $options);
    }
}

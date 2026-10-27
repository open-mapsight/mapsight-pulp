# Pulp PDO

Database source and destination handlers for Pulp pipelines, via PDO.

## Features

- **Write rows:** Insert each file's rows into a table in one transaction, optionally replacing all rows or the rows of one source first. Readers never see half a file.
- **Custom writes:** Run your own statements per file inside a transaction.
- **Query rows:** Emit the result of a query as a file, to merge stored state into a pipeline.
- **Lazy connections:** Pass a `PDO` or a callback that opens one on first use.

## Installation

```bash
composer require mapsight/pulp-pdo
```

## Write Rows

`write()` inserts rows into a table that already exists. It does not create or change the table: create it beforehand, or in the `setUp` option.

Each incoming file's content must be a list of rows, each an array of column names to values:

```php
[
    ['source' => 'stops.json', 'id' => '1', 'name' => 'Main Station'],
    ['source' => 'stops.json', 'id' => '2', 'name' => 'Town Hall'],
]
```

- Every row of a file needs the same columns, in any order.
- Values may be strings, numbers, `null`, booleans (stored as `0`/`1`) or arrays (stored as JSON).
- Content that does not fit fails the file like a database error does: its transaction is rolled back and the run stops. With `skipExceptions`, the error is logged and the next file follows.

A `Pulp::map()` usually turns decoded data into rows:

```php
use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpJSON;
use OpenMapsight\PulpPdo;

Pulp::start()
    ->pipe(Pulp::src('.*\.json', __DIR__ . '/stops'))
    ->pipe(PulpJSON::decodeJSON())
    ->pipe(Pulp::map(static function (File $file): File {
        $file->content = array_map(
            static fn(array $stop): array => ['source' => $file->fileName, 'id' => $stop['id'], 'name' => $stop['name']],
            $file->content,
        );

        return $file;
    }))
    ->pipe(PulpPdo::write(
        connection: static fn(): PDO => new PDO($dsn, $user, $password),
        table: 'stops',
        options: ['replace' => static fn(File $file): array => ['source' => $file->fileName]],
    ))
    ->run();
```

Options:

- `replace`: delete rows before inserting, in the same transaction. `true` deletes every row of the table; a callback returns the column values of the rows to delete.
- `chunkSize`: rows per `INSERT` statement (default `100`).
- `setUp`: a callback run once with the connection before the first write, outside any transaction. Create tables here: MySQL commits a transaction on `CREATE TABLE`.
- `skipExceptions`: log a failing file, roll it back, and continue.
- `logSkipExceptions`: `stderr`, `stdout`, or `false`.

`table` may also be a callback that returns the table name for a file. Table and column names may only contain letters, digits, and underscores (`schema.table` is allowed).

## Custom Writes

`transaction()` calls your callback with the connection and the file, committed when it returns and rolled back when it throws. It takes the `setUp`, `skipExceptions` and `logSkipExceptions` options of `write()`:

```php
->pipe(PulpPdo::transaction($pdo, static function (PDO $pdo, File $file): void {
    $pdo->prepare('DELETE FROM stops WHERE source = ?')->execute([$file->fileName]);
    // insert, update, compare with stored rows …
}))
```

## Query Rows

`query()` emits one file with the rows of a query, as a list of associative arrays, after the incoming files:

```php
Pulp::start()
    ->pipe(PulpPdo::query($pdo, 'SELECT id, name FROM stops WHERE source = ?', ['a.json'], 'stored.json'))
    ->pipe(Pulp::results(static function (array $files): void {
        // $files[0]->content: [['id' => …, 'name' => …], …]
    }))
    ->run();
```

The files pass through every handler, so a pipeline can continue after writing.

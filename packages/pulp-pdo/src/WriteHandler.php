<?php

declare(strict_types=1);

namespace OpenMapsight\pulppdo;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulp\Utils;
use PDO;
use RuntimeException;
use Throwable;

class WriteHandler extends AbstractHandler
{
    private ?Connection $connection = null;

    protected function getConstructorParamDefs(): array
    {
        return ['connection', 'table', 'options'];
    }

    public function onFile(File $file): void
    {
        try {
            $this->write($file);
        } catch (Throwable $err) {
            $err = new RuntimeException('Writing file "' . $file->fileName . '" to the database failed', 0, $err);

            if ($this->cp->options['skipExceptions'] ?? false === true) {
                Utils::log($this->cp->options['logSkipExceptions'] ?? 'stderr', $err);
            } else {
                throw $err;
            }
        }

        $this->pushFile($file);
    }

    private function write(File $file): void
    {
        $connection = $this->connection ??= new Connection($this->cp->connection, $this->cp->options['setUp'] ?? null);
        $table = is_string($this->cp->table) ? $this->cp->table : ($this->cp->table)($file);
        $quotedTable = $connection->quote((string) $table);
        $rows = $file->content;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new RuntimeException('Expected the file content to be a list of rows');
        }
        $columns = $rows === [] || !is_array($rows[0]) ? [] : array_map('strval', array_keys($rows[0]));
        foreach ($rows as $i => $row) {
            if (!is_array($row) || $row === [] || array_is_list($row)) {
                throw new RuntimeException('Row ' . $i . ' is not an array of column names to values');
            }
            $keys = array_map('strval', array_keys($row));
            $missing = array_diff($columns, $keys);
            $extra = array_diff($keys, $columns);
            if ($missing !== [] || $extra !== []) {
                throw new RuntimeException(sprintf(
                    'Row %d has other columns than row 0 (missing: %s; extra: %s)',
                    $i,
                    implode(', ', $missing) ?: '-',
                    implode(', ', $extra) ?: '-',
                ));
            }
        }

        $connection->transaction(function (PDO $pdo) use ($connection, $file, $quotedTable, $columns, $rows): void {
            $replace = $this->cp->options['replace'] ?? false;
            if ($replace === true) {
                $pdo->exec('DELETE FROM ' . $quotedTable);
            } elseif ($replace !== false && $replace !== null) {
                $where = $replace($file);
                if (!is_array($where) || $where === []) {
                    throw new RuntimeException('"replace" must return the column values of the rows to delete');
                }
                $conditions = array_map(
                    static fn(string $column): string => $connection->quote($column) . ' = ?',
                    array_keys($where),
                );
                $pdo->prepare('DELETE FROM ' . $quotedTable . ' WHERE ' . implode(' AND ', $conditions))
                    ->execute(array_map(self::value(...), array_values($where)));
            }

            if ($rows === []) {
                return;
            }
            $columnList = implode(', ', array_map($connection->quote(...), $columns));
            $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            foreach (array_chunk($rows, max(1, (int) ($this->cp->options['chunkSize'] ?? 100))) as $chunk) {
                $pdo->prepare(
                    'INSERT INTO ' . $quotedTable . ' (' . $columnList . ') VALUES '
                    . implode(', ', array_fill(0, count($chunk), $placeholders))
                )->execute(array_map(
                    self::value(...),
                    array_merge(...array_map(static fn(array $row): array => array_map(static fn(string $column): mixed => $row[$column], $columns), $chunk)),
                ));
            }
        });
    }

    private static function value(mixed $value): mixed
    {
        return match (true) {
            is_bool($value) => (int) $value,
            is_array($value) => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $value === null, is_scalar($value) => $value,
            default => throw new RuntimeException('Unsupported value of type ' . get_debug_type($value)),
        };
    }
}

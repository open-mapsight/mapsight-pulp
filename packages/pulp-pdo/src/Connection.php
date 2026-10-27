<?php

declare(strict_types=1);

namespace OpenMapsight\pulppdo;

use PDO;
use RuntimeException;

/**
 * Opens a connection given as a callback once, on first use, runs the
 * optional set-up on it, and quotes identifiers for its driver.
 */
final class Connection
{
    private ?PDO $pdo = null;

    /** @var PDO|callable(): PDO */
    private $connection;

    /** @var (callable(PDO): void)|null */
    private $setUp;

    /**
     * @param PDO|callable(): PDO $connection
     * @param (callable(PDO): void)|null $setUp Runs once, before the first statement and outside any transaction
     */
    public function __construct(PDO|callable $connection, ?callable $setUp = null)
    {
        $this->connection = $connection;
        $this->setUp = $setUp;
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $pdo = $this->connection instanceof PDO ? $this->connection : ($this->connection)();
            if (!$pdo instanceof PDO) {
                throw new RuntimeException('The connection callback must return a PDO instance');
            }
            if ($this->setUp !== null) {
                ($this->setUp)($pdo);
            }
            $this->pdo = $pdo;
        }

        return $this->pdo;
    }

    /**
     * Quotes a table or column name (`name` or `schema.name`). Only letters,
     * digits and underscores are accepted.
     */
    public function quote(string $identifier): string
    {
        $quote = $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? '`' : '"';
        $parts = explode('.', $identifier);
        foreach ($parts as $part) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $part) !== 1) {
                throw new RuntimeException('Invalid identifier "' . $identifier . '"');
            }
        }

        return implode('.', array_map(static fn(string $part): string => $quote . $part . $quote, $parts));
    }

    /**
     * Runs `$work` in a transaction: committed when it returns, rolled back
     * when it throws.
     *
     * @template T
     * @param callable(PDO): T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $work($pdo);
            $pdo->commit();

            return $result;
        } catch (\Throwable $err) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $err;
        }
    }
}

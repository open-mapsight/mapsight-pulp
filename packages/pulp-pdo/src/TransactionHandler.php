<?php

declare(strict_types=1);

namespace OpenMapsight\pulppdo;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulp\Utils;
use PDO;
use RuntimeException;
use Throwable;

class TransactionHandler extends AbstractHandler
{
    private ?Connection $connection = null;

    protected function getConstructorParamDefs(): array
    {
        return ['connection', 'write', 'options'];
    }

    public function onFile(File $file): void
    {
        $this->connection ??= new Connection($this->cp->connection, $this->cp->options['setUp'] ?? null);
        try {
            $this->connection->transaction(fn(PDO $pdo) => ($this->cp->write)($pdo, $file));
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
}

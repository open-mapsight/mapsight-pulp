<?php

declare(strict_types=1);

namespace OpenMapsight\pulppdo;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use PDO;

class QueryHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['connection', 'sql', 'params', 'fileName'];
    }

    public function onEnd(): void
    {
        $statement = (new Connection($this->cp->connection))->pdo()->prepare($this->cp->sql);
        $statement->execute($this->cp->params);

        $file = new File($this->cp->fileName);
        $file->content = $statement->fetchAll(PDO::FETCH_ASSOC);
        $this->pushFile($file);
    }
}

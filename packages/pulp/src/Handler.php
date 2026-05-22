<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use OpenMapsight\Pulp;

interface Handler
{
    public function handleFile(?File $file = null): void;

    public function setNextHandler(Handler $handler): void;

    public function setPulp(Pulp $pulp): void;
}

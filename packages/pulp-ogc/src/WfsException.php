<?php

declare(strict_types=1);

namespace OpenMapsight\pulpogc;

use RuntimeException;

/** An OGC exception report returned by the service. */
class WfsException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $exceptionCode = null,
        public readonly ?string $locator = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}

<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

/**
 * Deletes a temp path when the last reference is released.
 *
 * File clones share one instance, so a shallow clone does not unlink early.
 */
final class SharedTempPath
{
    public function __construct(private readonly string $path) {}

    public function __destruct()
    {
        @unlink($this->path);
    }
}

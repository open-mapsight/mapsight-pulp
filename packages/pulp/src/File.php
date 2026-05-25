<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use RuntimeException;

/**
 * @property string content
 * @property array stats (@see \stats())
 */
class File
{
    /** @var string $srcFileName */
    public $srcFileName;

    private array $___data = [];

    /**
     * File constructor.
     */
    public function __construct(public $fileName, ?string $srcFileName = null)
    {
        $this->srcFileName = empty($srcFileName) ? $this->fileName : $srcFileName;
    }

    public static function fromPath(string $path, ?string $fileName = null): File
    {
        $file = new File(
            $fileName ?: $path,
            $path
        );

        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Unable to load file \"" . $path . "\"");
        }
        $file->content = $content;

        $stats = @stat($path);
        if ($stats !== false) {
            $file->stats = $stats;
        }

        return $file;
    }

    public function &__get(string $key): mixed
    {
        return $this->___data[$key];
    }

    public function __set(string $key, mixed $value)
    {
        $this->___data[$key] = $value;
    }

    public function __isset(string $key)
    {
        return isset($this->___data[$key]) && $this->___data[$key] !== null;
    }

    public function __unset(string $key)
    {
        unset($this->___data[$key]);
    }
}

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
    private ?string $___contentPath = null;

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

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Unable to load file \"" . $path . "\"");
        }

        $file->___contentPath = $path;

        $stats = @stat($path);
        if ($stats !== false) {
            $file->stats = $stats;
        }

        return $file;
    }

    /**
     * @return resource
     */
    public function stream()
    {
        if (array_key_exists('content', $this->___data)) {
            if (!is_string($this->___data['content'])) {
                throw new RuntimeException('Unable to create stream from non-string file content');
            }

            $stream = fopen('php://temp', 'r+');
            if ($stream === false) {
                throw new RuntimeException('Unable to create temporary file stream');
            }

            fwrite($stream, $this->___data['content']);
            rewind($stream);

            return $stream;
        }

        if ($this->___contentPath === null) {
            throw new RuntimeException('File "' . $this->fileName . '" has no streamable content');
        }

        $stream = @fopen($this->___contentPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open file "' . $this->___contentPath . '"');
        }

        return $stream;
    }

    public function &__get(string $key): mixed
    {
        if ($key === 'content' && !array_key_exists('content', $this->___data) && $this->___contentPath !== null) {
            $content = @file_get_contents($this->___contentPath);
            if ($content === false) {
                throw new RuntimeException('Unable to load file "' . $this->___contentPath . '"');
            }

            $this->___data['content'] = $content;
        }

        if (!array_key_exists($key, $this->___data)) {
            $this->___data[$key] = null;
        }

        return $this->___data[$key];
    }

    public function __set(string $key, mixed $value)
    {
        $this->___data[$key] = $value;
    }

    public function __isset(string $key)
    {
        if ($key === 'content' && $this->___contentPath !== null) {
            return true;
        }

        return isset($this->___data[$key]) && $this->___data[$key] !== null;
    }

    public function __unset(string $key)
    {
        unset($this->___data[$key]);
    }
}

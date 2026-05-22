<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulp\DebugHandler;
use OpenMapsight\pulp\DeleteHandler;
use OpenMapsight\pulp\DestHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulp\FileSwitchHandler;
use OpenMapsight\pulp\FilterHandler;
use OpenMapsight\pulp\Handler;
use OpenMapsight\pulp\MapHandler;
use OpenMapsight\pulp\MergeHandler;
use OpenMapsight\pulp\ResultsHandler;
use OpenMapsight\pulp\ServeHttpHandler;
use OpenMapsight\pulp\ShadowHandler;
use OpenMapsight\pulp\SrcFileHandler;
use OpenMapsight\pulp\SrcHandler;
use OpenMapsight\pulp\SrcHttpHandler;
use OpenMapsight\pulp\Utf8DecodeHandler;
use OpenMapsight\pulp\Utf8EncodeHandler;
use RuntimeException;

class Pulp implements Handler
{
    private ?Handler $firstHandler = null;
    private ?Handler $lastHandler = null;
    private array $result = [];

    public static function start(): Pulp
    {
        return new self();
    }

    public static function src($pattern, $directory = null): SrcHandler
    {
        return new SrcHandler($pattern, $directory);
    }

    /**
     * ## Options
     * * `skipExceptions`: Skips file (no virtual file is emitted) on exception, and logs.
     *   (default: `false`)
     * * `logSkipExceptions`: (default: `"stderr"`)
     *   * `false`: no logging
     *   * `"stdout"`: log to stdout
     *   * `"stderr"`: log to stderr
     */
    public static function srcFile(
        $fileName,
        $aliasFileName = null,
        array $options = []
    ): SrcFileHandler {
        return new SrcFileHandler($fileName, $aliasFileName, $options);
    }

    /**
     * ## Options
     * * `skipExceptions`: Skips file (no virtual file is emitted) on exception, and logs.
     *   (default: `false`)
     * * `logSkipExceptions`: (default: `"stderr"`)
     *   * `false`: no logging
     *   * `"stdout"`: log to stdout
     *   * `"stderr"`: log to stderr
     */
    public static function srcHttp(
        $method,
        $uri,
        array $guzzleOptions,
        $aliasFileName,
        array $options = []
    ): SrcHttpHandler {
        return new SrcHttpHandler(
            $method,
            $uri,
            $guzzleOptions,
            $aliasFileName,
            $options
        );
    }

    /**
     * ## Options
     * * `flush`: `fflush`es files to disk (default: `false`)
     * * `handleLogicallyEmpty`: skips write if the "virtual" file has its `isLogicallyEmpty`
     *   parameter set to `true` and the size of the "physical" destination file has the same
     *   length as the value in `logicallyEmptyContent`, **REGARDLESS OF THE ACTUALL CONTENT**.
     *   If the `isLogicallyEmpty` is set, but the file length is different,
     *   `logicallyEmptyContent` gets written to the file.
     *
     *   **WARNING**: overwrites the "physical" destination file with `logicallyEmptyContent` if
     *   the "virtual" files `isLogicallyEmpty` parameter is set to `true`! So watch out if you're
     *   reusing a `File` object, and set `isLogicallyEmpty` to `false` to be sure, that your file
     *   doesn't get destroyed.
     *
     *   **WARNING**: **DON'T USE THIS OPTION, IT IS A FOOTGUN**
     *
     *   (default: `false`)
     * * `logicallyEmptyContent`: see `handleLogicallyEmpty` option (default: `""`)
     * * `skipExceptions`: Ignores exceptions and logs them. (default: `false`)
     * * `logSkipExceptions`: (default: `"stderr"`)
     *   * `false`: no logging
     *   * `"stdout"`: log to stdout
     *   * `"stderr"`: log to stderr
     *
     * @param {string} $directory The path that is used as the base directory path to store the
     * files
     * @param {{
     *    flush?: boolean,
     *    skipWriteOnEmptyFile?: boolean,
     *    logicallyEmptyContent?: [u8],
     * }} [$options]
     */
    public static function dest($directory, array $options = []): DestHandler
    {
        return new DestHandler($directory, $options);
    }

    public static function delete(): DeleteHandler
    {
        return new DeleteHandler();
    }

    public static function filter($callback): FilterHandler
    {
        return new FilterHandler($callback);
    }

    public static function map($callback): MapHandler
    {
        return new MapHandler($callback);
    }

    public static function utf8encode(): Utf8EncodeHandler
    {
        return new Utf8EncodeHandler();
    }

    public static function utf8decode(): Utf8DecodeHandler
    {
        return new Utf8DecodeHandler();
    }

    public static function debug($length = 30): DebugHandler
    {
        return new DebugHandler($length);
    }

    public static function merge(): MergeHandler
    {
        $pulps = func_get_args();
        foreach ($pulps as $p) {
            if (!($p instanceof self)) {
                throw new RuntimeException('Not a Pulp');
            }
        }

        return new MergeHandler($pulps);
    }

    public static function shadow($cb): ShadowHandler
    {
        return new ShadowHandler($cb);
    }

    public static function results($cb): ResultsHandler
    {
        return new ResultsHandler($cb);
    }

    public static function serveHttp(): ServeHttpHandler
    {
        return new ServeHttpHandler();
    }

    /**
     * @param array $patterns
     * @param callable $defaultCb
     *
     * @return pulp\FileSwitchHandler
     */
    public static function fileSwitch(array $patterns, ?callable $defaultCb = null): FileSwitchHandler
    {
        return new FileSwitchHandler($patterns, $defaultCb);
    }

    public function pipe(Handler $handler): static
    {
        if (!$this->firstHandler instanceof Handler) {
            $this->firstHandler = $handler;
        }

        if ($this->lastHandler instanceof Handler) {
            $this->lastHandler->setNextHandler($handler);
        }

        $handler->setPulp($this);

        $this->lastHandler = $handler;

        return $this;
    }

    /**
     * @return Handler|null
     */
    public function getFirstHandler(): ?Handler
    {
        return $this->firstHandler;
    }

    /**
     * @return Handler|null
     */
    public function getLastHandler(): ?Handler
    {
        return $this->lastHandler;
    }

    public function addResultFile(File $result): void
    {
        $this->result[] = $result;
    }

    /**
     * @param File[]|File $files
     *
     * @return File[]
     * @throws RuntimeException
     */
    public function run($files = null): array
    {
        if ($files === null) {
            $files = [];
        }

        if (!is_array($files)) {
            $files = [$files];
        }

        if (!$this->firstHandler instanceof Handler) {
            return $files;
        }

        foreach ($files as $file) {
            if (!($file instanceof File)) {
                throw new RuntimeException('Not a OpenMapsight\pulp\File object');
            }

            $this->firstHandler->handleFile($file);
        }

        $this->firstHandler->handleFile();

        return $this->result;
    }

    public function handleFile(?File $file = null): void
    {
        $this->firstHandler->handleFile($file);
    }

    public function setNextHandler(Handler $handler): void
    {
        $this->lastHandler->setNextHandler($handler);
    }

    public function setPulp(Pulp $pulp): void
    {
        $this->lastHandler->setPulp($pulp);
    }
}

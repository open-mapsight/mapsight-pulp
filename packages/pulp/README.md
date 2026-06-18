# Pulp

Stream-like file processing for PHP, inspired by Gulp.

Pulp moves `File` objects through a chain of handlers. Source handlers create files, transform handlers edit or replace them, branching handlers fan work out, and destination/result handlers write or collect the final output.

## Features

- **Pipeline API:** Compose file processing as `Pulp::start()->pipe(...)->run()`.
- **Virtual files:** Handlers pass `OpenMapsight\pulp\File` objects with `fileName`, `srcFileName`, and dynamic metadata.
- **Lazy file content:** Files from disk are not read until `$file->content` is accessed.
- **Stream access:** Large-file handlers can call `$file->stream()` to read without loading full content.
- **Branching:** Use `split`, `merge`, `shadow`, and `fileSwitch` for common flow-control patterns.
- **Extensible:** Implement handlers directly or build package-level helpers around them.

## Quick Start

```php
use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;

Pulp::start()
    ->pipe(Pulp::src('.*\.txt', __DIR__ . '/input'))
    ->pipe(Pulp::map(static function (File $file): File {
        $file->content = strtoupper($file->content);

        return $file;
    }))
    ->pipe(Pulp::dest(__DIR__ . '/output'))
    ->run();
```

## Core Concepts

### Pipelines

A pipeline is a chain of handlers:

```php
Pulp::start()
    ->pipe(Pulp::src('.*\.json', __DIR__ . '/data'))
    ->pipe(/* handler */)
    ->pipe(Pulp::dest(__DIR__ . '/result'))
    ->run();
```

`run()` starts the chain. If no source handler is present, you can pass files directly:

```php
$file = new File('example.txt');
$file->content = 'Hello';

$results = Pulp::start()
    ->pipe(Pulp::map(static fn(File $file): File => $file))
    ->run($file);
```

### Files

`OpenMapsight\pulp\File` represents a virtual file.

- `$file->fileName`: the pipeline-relative file name.
- `$file->srcFileName`: the original source name/path.
- `$file->content`: dynamic content property. For files sourced from disk, this lazy-loads the full file on first access.
- `$file->stream()`: returns a readable stream. For path-backed files this opens the source file directly; for generated string content it creates a temporary stream.
- Additional properties can be attached dynamically, for example `$file->stats` or `$file->isLogicallyEmpty`.

Use `$file->content` for normal transforms. Use `$file->stream()` in handlers that need to process large files without loading them completely.

## API

### Pulp::start()

Creates an empty pipeline.

```php
$pulp = Pulp::start();
```

### Pulp::src($patterns, $directory = '.')

Creates files from matching paths.

- `$patterns`: a string, array of strings, file path, or `File`.
- `$directory`: base directory for recursive matching.

Patterns are regular expressions matched against relative file names.

```php
Pulp::start()
    ->pipe(Pulp::src('.*\.csv', __DIR__ . '/data'))
    ->run();
```

### Pulp::srcFile($fileName, $aliasFileName = null, array $options = [])

Creates one file from a path. If `$aliasFileName` is provided, it becomes the virtual `fileName`.

### Pulp::srcHttp($method, $uri, array $guzzleOptions, $aliasFileName, array $options = [])

Creates one file from an HTTP response body.

### Pulp::dest($directory, array $options = [])

Writes incoming files into `$directory` using each file's `fileName`.

```php
->pipe(Pulp::dest(__DIR__ . '/result'))
```

Common options:

- `flush`: call `fflush()` after writing.
- `skipExceptions`: log write errors and continue.
- `logSkipExceptions`: `stderr`, `stdout`, or `false`.

### Pulp::map($callback)

Transforms or drops files.

```php
->pipe(Pulp::map(static function (File $file): ?File {
    if ($file->fileName === 'skip.txt') {
        return null;
    }

    $file->content .= "\n";

    return $file;
}))
```

### Pulp::filter($callback)

Keeps only files where the callback returns truthy.

```php
->pipe(Pulp::filter(static fn(File $file): bool => str_ends_with($file->fileName, '.json')))
```

### Pulp::results($callback)

Collects all files that reach this handler and calls the callback at the end.

```php
->pipe(Pulp::results(static function (array $files): void {
    // inspect results
}))
```

### Pulp::split(...$pipelines)

Feeds the same incoming files into multiple branch pipelines and merges each branch's results back into the main pipeline.

Use this when one source should produce several outputs.

```php
Pulp::start()
    ->pipe(Pulp::src('.*\.txt', __DIR__ . '/input'))
    ->pipe(Pulp::split(
        static fn(Pulp $p): Pulp => $p->pipe(Pulp::map(static function (File $file): File {
            $file->fileName = 'upper-' . $file->fileName;
            $file->content = strtoupper($file->content);

            return $file;
        })),
        static fn(Pulp $p): Pulp => $p->pipe(Pulp::map(static function (File $file): File {
            $file->fileName = 'lower-' . $file->fileName;
            $file->content = strtolower($file->content);

            return $file;
        })),
    ))
    ->pipe(Pulp::dest(__DIR__ . '/result'))
    ->run();
```

Branches may be `Pulp` instances or callbacks receiving a new `Pulp` instance.

### Pulp::merge(...$pulps)

Runs independent pipelines and merges their output.

```php
$a = Pulp::start()->pipe(Pulp::src('.*\.json', __DIR__ . '/a'));
$b = Pulp::start()->pipe(Pulp::src('.*\.json', __DIR__ . '/b'));

Pulp::start()
    ->pipe(Pulp::merge($a, $b))
    ->pipe(Pulp::dest(__DIR__ . '/result'))
    ->run();
```

Use `merge` for independent sources. Use `split` when you already have one incoming stream and want multiple outputs from it.

### Pulp::shadow($callback)

Taps the stream into a side pipeline without changing the main stream.

```php
->pipe(Pulp::shadow(static fn(Pulp $p): Pulp => $p
    ->pipe(Pulp::debug())
))
```

`shadow` is useful for diagnostics or side effects. Its branch results are not merged back. Use `split` if branch output should continue downstream.

### Pulp::fileSwitch(array $patterns, ?callable $defaultCb = null)

Routes files into different sub-pipelines by file name.

```php
->pipe(Pulp::fileSwitch([
    '.*\.json' => static fn(Pulp $p): Pulp => $p->pipe(/* JSON handlers */),
    '.*\.xml' => static fn(Pulp $p): Pulp => $p->pipe(/* XML handlers */),
], static fn(Pulp $p): Pulp => $p))
```

### Pulp::debug($length = 30)

Prints a short preview for each file.

### Pulp::delete()

Deletes each file's `srcFileName` from disk.

### Pulp::utf8encode() / Pulp::utf8decode()

Converts string content between ISO-8859-1 and UTF-8.

### Pulp::serveHttp()

Sends file content as an HTTP response.

## Common Patterns

### Read, Transform, Write

```php
Pulp::start()
    ->pipe(Pulp::src('.*\.txt', __DIR__ . '/input'))
    ->pipe(Pulp::map(static function (File $file): File {
        $file->content = trim($file->content) . "\n";

        return $file;
    }))
    ->pipe(Pulp::dest(__DIR__ . '/output'))
    ->run();
```

### Fan Out One Source Into Multiple Outputs

```php
Pulp::start()
    ->pipe(Pulp::src('.*\.txt', __DIR__ . '/input'))
    ->pipe(Pulp::split(
        static fn(Pulp $p): Pulp => $p->pipe(/* branch A */),
        static fn(Pulp $p): Pulp => $p->pipe(/* branch B */),
    ))
    ->pipe(Pulp::dest(__DIR__ . '/output'))
    ->run();
```

### Process Large Files With Streams

```php
->pipe(Pulp::map(static function (File $file): File {
    $stream = $file->stream();

    try {
        while (($line = fgets($stream)) !== false) {
            // Process without loading the full file.
        }
    } finally {
        fclose($stream);
    }

    return $file;
}))
```

## Writing Custom Handlers

Most handlers extend `OpenMapsight\pulp\AbstractHandler`.

```php
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class UppercaseHandler extends AbstractHandler
{
    public function onFile(File $file): void
    {
        $file->content = strtoupper($file->content);
        $this->pushFile($file);
    }
}
```

Handlers can override:

- `onStart()`: called before the first file.
- `onFile(File $file)`: called for each file.
- `onEnd()`: called when the stream ends.

Use `$this->pushFile($file)` to pass output to the next handler. A handler may emit zero, one, or many files.

Constructor arguments are declared with `getConstructorParamDefs()` and accessed via `$this->cp`.

```php
class PrefixHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['prefix'];
    }

    public function onFile(File $file): void
    {
        $file->content = $this->cp->prefix . $file->content;
        $this->pushFile($file);
    }
}
```

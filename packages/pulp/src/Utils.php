<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use RuntimeException;
use Throwable;

class Utils
{
    /**
     * Create `$directory` (and parents) if needed, then return the path.
     *
     * @throws RuntimeException
     */
    public static function ensureDirectory(string $directory, int $permissions = 0775): string
    {
        if ($directory === '') {
            throw new RuntimeException('Directory must not be empty');
        }

        if (!is_dir($directory) && !mkdir($directory, $permissions, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create directory "%s"', $directory));
        }

        return $directory;
    }

    public static function matchFileName($pattern, $fileName): int|false
    {
        $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';

        return preg_match($pattern, (string) $fileName);
    }

    /**
     * @param false | "stdout" | "stderr" $to
     * @param string | Throwable $msg
     * @throws RuntimeException
     */
    public static function log($to, $msg): void
    {
        if ($to === false) {
            return;
        }

        if (is_string($msg)) {
            $msgStr = $msg;
        } elseif ($msg instanceof Throwable) {
            $msgStr = $msg::class . ' - ' . $msg->getMessage();
            $msgStr .= ' in ' . $msg->getFile() . ':' . $msg->getLine() . "\n";
            $source = $msg->getPrevious();
            while ($source instanceof Throwable) {
                $msgStr .= '  caused by: ' . $source::class . ' - ' . $source->getMessage();
                $msgStr .= ' in ' . $source->getFile() . ':' . $source->getLine() . "\n";
                $source = $source->getPrevious();
            }
        } else {
            throw new RuntimeException('Unsupported `$msg` type, expected: `string` or `\Throwable`');
        }

        if ($to === 'stderr') {
            fwrite(STDERR, $msgStr);
        } elseif ($to === 'stdout') {
            fwrite(STDOUT, $msgStr);
        } else {
            throw new RuntimeException('Unsupported logging destination "' . $to . '"');
        }
    }
}

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

    /**
     * Undo UTF-8 text that was encoded twice.
     *
     * A source that reads UTF-8 as ISO-8859-1 and writes UTF-8 again turns "ö" into "Ã¶"
     * and "ß" into "Ã" followed by U+009F. The string is converted back only when all of
     * it converts losslessly and the result is valid UTF-8, so correct text, ASCII and
     * strings that mix both forms stay unchanged.
     */
    public static function repairDoubleEncodedUtf8(string $value): string
    {
        // A lead byte (U+00C2–U+00F4) followed by a continuation byte (U+0080–U+00BF),
        // each read as one Latin-1 character.
        if (preg_match('/[\x{00C2}-\x{00F4}][\x{0080}-\x{00BF}]/u', $value) !== 1) {
            return $value;
        }
        if (preg_match('/[^\x{0000}-\x{00FF}]/u', $value) === 1) {
            return $value;
        }
        $bytes = mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
        if (!mb_check_encoding($bytes, 'UTF-8')) {
            return $value;
        }

        return $bytes;
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

<?php

declare(strict_types=1);

namespace OpenMapsight\pulpjson;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use OpenMapsight\pulp\Utils;
use RuntimeException;
use Throwable;

class DecodeJSONHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['options'];
    }

    public static function removeUtf8BOM(string $text): string
    {
        $bom = pack('H*', 'EFBBBF');
        return preg_replace("/^$bom/", '', $text);
    }

    public static function ensureUtf8Encoding(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }

        return $text;
    }

    /**
     * @throws RuntimeException
     */
    public function onFile(File $file): void
    {
        try {
            $input = $file->content;
            $input = self::removeUtf8BOM($input);
            $input = self::ensureUtf8Encoding($input);

            $res = @json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            if ($res === null) {
                throw new RuntimeException('Unable to decode JSON');
            }
        } catch (Throwable $err) {
            $err = new RuntimeException(
                'Decoding json file "' . $this->cp->fileName . '" failed',
                0,
                $err
            );

            if ($this->cp->options['skipExceptions'] ?? false === true) {
                Utils::log($this->cp->options['logSkipExceptions'] ?? 'stderr', $err);

                // do not push file
                return;
            }

            throw $err;
        }

        $file->content = $res;

        // not in the try block to not catch exceptions from other handlers
        $this->pushFile($file);
    }
}

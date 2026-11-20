<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

/**
 * Repair text that was encoded as UTF-8 twice. String content is repaired as a whole;
 * array content (for example decoded JSON) has every string value repaired, at any depth.
 * Keys and other values stay unchanged.
 */
class RepairDoubleEncodedUtf8Handler extends AbstractHandler
{
    public function onFile(File $file): void
    {
        $content = $file->content;
        if (is_string($content)) {
            $file->content = Utils::repairDoubleEncodedUtf8($content);
        } elseif (is_array($content)) {
            $file->content = self::values($content);
        }
        $this->pushFile($file);
    }

    private static function values(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = Utils::repairDoubleEncodedUtf8($value);
            } elseif (is_array($value)) {
                $values[$key] = self::values($value);
            }
        }

        return $values;
    }
}

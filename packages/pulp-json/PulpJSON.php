<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulpjson\DecodeJSONHandler;
use OpenMapsight\pulpjson\EncodeJSONHandler;
use OpenMapsight\pulpjson\EncodeJSONPHandler;

class PulpJSON
{
    /**
     * ## Options
     * * `skipExceptions`: Skips file (no virtual file is emitted) on exception, and logs.
     *   (default: `false`)
     * * `logSkipExceptions`: (default: `"stderr"`)
     *   * `false`: no logging
     *   * `"stdout"`: log to stdout
     *   * `"stderr"`: log to stderr
     *
     * Decodes data from JSON
     *
     * @return pulpjson\DecodeJSONHandler
     * @link http://php.net/manual/en/function.json-decode.php
     *
     * @see  \json_decode
     */
    public static function decodeJSON(array $options = []): DecodeJSONHandler
    {
        return new DecodeJSONHandler($options);
    }

    /**
     * Encodes data as JSON
     *
     * @param int|null $options [optional] Bitmask
     *
     * @return pulpjson\EncodeJSONHandler
     * @see  \json_encode
     * @link http://php.net/manual/en/function.json-encode.php
     *
     */
    public static function encodeJSON(null | int $options = null): EncodeJSONHandler
    {
        return new EncodeJSONHandler($options);
    }

    /**
     * Encodes data as JSONP
     *
     * @param string $paddingName [optional] Padding / prefix
     * @param int|null $options [optional] Bitmask
     *
     * @return pulpjson\EncodeJSONPHandler
     * @link http://php.net/manual/en/function.json-encode.php
     *
     * @see  \json_encode
     */
    public static function encodeJSONP(string $paddingName = '_', null | int $options = null): EncodeJSONPHandler
    {
        return new EncodeJSONPHandler($paddingName, $options);
    }
}

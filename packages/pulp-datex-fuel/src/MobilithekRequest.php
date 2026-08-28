<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexfuel;

use OpenMapsight\Pulp;
use OpenMapsight\pulp\SrcHttpHandler;
use RuntimeException;

class MobilithekRequest
{
    public const SUBSCRIPTION_URL = 'https://mobilithek.info:8443/mobilithek/api/v1.0/subscription';

    /**
     * @param array<string, mixed> $guzzleOptions
     * @return array<string, mixed>
     */
    public static function guzzleOptions(
        string $subscriptionId,
        string $certPath,
        string $certPassword,
        ?string $ifModifiedSince = null,
        array $guzzleOptions = [],
    ): array {
        if ($certPath === '' || $certPassword === '') {
            throw new RuntimeException('Mobilithek client certificate path and password must be supplied');
        }

        $headers = [
            'Accept-Encoding' => 'gzip',
            'Accept' => 'application/xml, text/xml, application/json, */*',
        ];
        if ($ifModifiedSince !== null && $ifModifiedSince !== '') {
            $headers['If-Modified-Since'] = $ifModifiedSince;
        }

        $defaults = [
            'timeout' => 90,
            'decode_content' => true,
            'http_errors' => false,
            'headers' => $headers,
            'query' => ['subscriptionID' => $subscriptionId],
            'curl' => [
                CURLOPT_SSLCERT => $certPath,
                CURLOPT_SSLCERTPASSWD => $certPassword,
                CURLOPT_SSLCERTTYPE => 'P12',
            ],
        ];

        $merged = array_replace($defaults, $guzzleOptions);
        $merged['headers'] = array_replace($defaults['headers'], $guzzleOptions['headers'] ?? []);
        $merged['query'] = array_replace($defaults['query'], $guzzleOptions['query'] ?? []);
        $merged['curl'] = array_replace($defaults['curl'], $guzzleOptions['curl'] ?? []);

        return $merged;
    }

    /**
     * @param array<string, mixed> $guzzleOptions
     * @param array<string, mixed> $options
     */
    public static function srcHttp(
        string $subscriptionId,
        string $certPath,
        string $certPassword,
        ?string $ifModifiedSince = null,
        string $aliasFileName = 'mobilithek.xml',
        array $guzzleOptions = [],
        array $options = [],
    ): SrcHttpHandler {
        return Pulp::srcHttp(
            'GET',
            self::SUBSCRIPTION_URL,
            self::guzzleOptions(
                $subscriptionId,
                $certPath,
                $certPassword,
                $ifModifiedSince,
                $guzzleOptions
            ),
            $aliasFileName,
            $options
        );
    }
}

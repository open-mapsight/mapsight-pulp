<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulp\SrcHttpHandler;
use OpenMapsight\pulpmobilithek\MobilithekRequest;

class PulpMobilithek
{
    public const SUBSCRIPTION_URL = MobilithekRequest::SUBSCRIPTION_URL;

    /**
     * Configures `Pulp::srcHttp` for a Mobilithek subscription GET.
     *
     * Certificate path, password, and subscription ID stay caller-supplied.
     *
     * @param array<string, mixed> $guzzleOptions
     * @param array<string, mixed> $options
     */
    public static function srcMobilithek(
        string $subscriptionId,
        string $certPath,
        string $certPassword,
        ?string $ifModifiedSince = null,
        string $aliasFileName = 'mobilithek.bin',
        array $guzzleOptions = [],
        array $options = [],
    ): SrcHttpHandler {
        return MobilithekRequest::srcHttp(
            $subscriptionId,
            $certPath,
            $certPassword,
            $ifModifiedSince,
            $aliasFileName,
            $guzzleOptions,
            $options
        );
    }

    /**
     * Default Mobilithek Guzzle options: gzip, P12 client cert, subscription query.
     *
     * @param array<string, mixed> $guzzleOptions
     * @return array<string, mixed>
     */
    public static function mobilithekGuzzleOptions(
        string $subscriptionId,
        string $certPath,
        string $certPassword,
        ?string $ifModifiedSince = null,
        array $guzzleOptions = [],
    ): array {
        return MobilithekRequest::guzzleOptions(
            $subscriptionId,
            $certPath,
            $certPassword,
            $ifModifiedSince,
            $guzzleOptions
        );
    }
}

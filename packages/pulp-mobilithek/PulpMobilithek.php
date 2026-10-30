<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulp\SrcHttpHandler;
use OpenMapsight\pulpmobilithek\DrainSubscriptionHandler;
use OpenMapsight\pulpmobilithek\MobilithekRequest;

class PulpMobilithek
{
    public const SUBSCRIPTION_URL = MobilithekRequest::SUBSCRIPTION_URL;

    /**
     * Drain a delta subscription until 304/204 or maxPackets (default 200).
     * A null cursor starts at the oldest buffered snapshot. Emits packets in order.
     * options: client (Guzzle), maxPackets, probeLatest (default true).
     *
     * @param array<string, mixed> $guzzleOptions
     * @param array<string, mixed> $options
     */
    public static function drainMobilithek(
        string $subscriptionId,
        string $certPath,
        string $certPassword,
        ?string $ifModifiedSince = null,
        string $aliasFileName = 'mobilithek.bin',
        array $guzzleOptions = [],
        array $options = [],
    ): DrainSubscriptionHandler {
        return new DrainSubscriptionHandler(
            MobilithekRequest::guzzleOptions($subscriptionId, $certPath, $certPassword, null, $guzzleOptions),
            $ifModifiedSince,
            $aliasFileName,
            $options
        );
    }

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

<?php

declare(strict_types=1);

namespace OpenMapsight\pulpmobilithek;

use GuzzleHttp\Client;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use RuntimeException;

/** Drain the ordered delta buffer, emitting one File per packet. */
class DrainSubscriptionHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['guzzleOptions', 'ifModifiedSince', 'aliasFileName', 'options'];
    }

    public function onEnd(): void
    {
        $client = $this->cp->options['client'] ?? new Client();
        if (!$client instanceof Client) {
            throw new RuntimeException('Mobilithek drain option "client" must be a GuzzleHttp\\Client');
        }
        $maxPackets = $this->cp->options['maxPackets'] ?? 200;
        if (!is_int($maxPackets) || $maxPackets < 1) {
            throw new RuntimeException('Mobilithek drain maxPackets must be a positive integer');
        }
        // Starting without a cursor must retrieve the baseline, not the newest delta.
        $cursor = $this->cp->ifModifiedSince ?: 'Thu, 01 Jan 1970 00:00:00 GMT';
        if (strtotime($cursor) === false) {
            throw new RuntimeException('Mobilithek drain cursor must be a valid HTTP date');
        }
        $seen = [];
        $caughtUp = false;
        for ($i = 0; $i < $maxPackets; $i++) {
            $file = $this->fetch($client, $cursor);
            if ($file === null) {
                $caughtUp = true;
                break;
            }
            $next = $file->httpLastModified;
            if (strtotime($next) <= strtotime($cursor)) {
                throw new RuntimeException('Mobilithek delta cursor did not advance');
            }
            $seen[$this->packetKey($file)] = true;
            $this->pushFile($file);
            $cursor = $next;
        }
        // Never skip a backlog by advancing to the latest packet on a bounded run.
        // This probe only mitigates collisions at the head; historical same-second
        // packets are not addressable through If-Modified-Since.
        if ($caughtUp && ($this->cp->options['probeLatest'] ?? true)) {
            $file = $this->fetch($client, null);
            if ($file !== null && strtotime($file->httpLastModified) >= strtotime($cursor) && !isset($seen[$this->packetKey($file)])) {
                $file->mobilithekAdvanceCursor = false;
                $this->pushFile($file);
            }
        }
    }

    private function fetch(Client $client, ?string $cursor): ?File
    {
        $options = $this->cp->guzzleOptions;
        // Remove cursor headers case-insensitively, including caller overrides.
        foreach (array_keys($options['headers'] ?? []) as $name) {
            if (strcasecmp($name, 'If-Modified-Since') === 0 || strcasecmp($name, 'If-None-Match') === 0) {
                unset($options['headers'][$name]);
            }
        }
        if ($cursor !== null) {
            $options['headers']['If-Modified-Since'] = $cursor;
        }
        $response = $client->request('GET', MobilithekRequest::SUBSCRIPTION_URL, $options);
        $status = $response->getStatusCode();
        if ($status === 304 || $status === 204) {
            return null;
        }
        if ($status !== 200) {
            throw new RuntimeException('Mobilithek delta request returned HTTP ' . $status);
        }
        $lastModified = $response->getHeaderLine('Last-Modified');
        if ($lastModified === '' || strtotime($lastModified) === false) {
            throw new RuntimeException('Mobilithek delta response has no valid Last-Modified');
        }
        $file = new File($this->cp->aliasFileName);
        $file->content = (string) $response->getBody();
        $file->httpStatus = $status;
        $file->httpLastModified = $lastModified;
        $file->httpEtag = $response->getHeaderLine('ETag');
        $file->httpType = $response->getHeaderLine('Type');

        return $file;
    }

    private function packetKey(File $file): string
    {
        return $file->httpEtag !== '' ? $file->httpEtag : hash('sha256', $file->content);
    }
}

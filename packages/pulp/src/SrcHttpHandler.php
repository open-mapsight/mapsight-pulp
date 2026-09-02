<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use GuzzleHttp\Client;
use RuntimeException;
use Throwable;

class SrcHttpHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['method', 'uri', 'guzzleOptions', 'aliasFileName', 'options'];
    }

    /**
     * @throws RuntimeException
     */
    public function onEnd(): void
    {
        $file = null;
        try {
            $client = $this->client();
            $guzzleOptions = $this->cp->guzzleOptions;
            $sinkPath = $this->resolveSinkPath();
            if ($sinkPath !== null) {
                $guzzleOptions['sink'] = $sinkPath;
            }

            $res = $client->request(
                $this->cp->method,
                $this->cp->uri,
                $guzzleOptions
            );

            $statusCode = $res->getStatusCode();
            $this->assertSuccessStatus($statusCode);

            if ($sinkPath !== null) {
                $tmpFile = File::fromPath($sinkPath, $this->cp->aliasFileName);
            } else {
                $tmpFile = new File($this->cp->aliasFileName);
                $tmpFile->content = (string) $res->getBody();
            }

            $tmpFile->httpStatus = $statusCode;
            $tmpFile->httpLastModified = $res->getHeaderLine('Last-Modified');
            $tmpFile->httpEtag = $res->getHeaderLine('ETag');
            $tmpFile->httpType = $res->getHeaderLine('Type');
            $file = $tmpFile;
        } catch (Throwable $err) {
            if (!$err instanceof RuntimeException || $err->getPrevious() !== null) {
                $err = new RuntimeException(
                    'Http ' . $this->cp->method . ' on "' . $this->cp->uri . '" failed',
                    0,
                    $err
                );
            }

            if ($this->cp->options['skipExceptions'] ?? false === true) {
                Utils::log($this->cp->options['logSkipExceptions'] ?? 'stderr', $err);
            } else {
                throw $err;
            }
        }

        if ($file instanceof File) {
            // not in the try block to not catch exceptions from other handlers
            $this->pushFile($file);
        }
    }

    private function client(): Client
    {
        $client = $this->cp->options['client'] ?? new Client();
        if (!$client instanceof Client) {
            throw new RuntimeException('srcHttp option "client" must be a GuzzleHttp\\Client');
        }

        return $client;
    }

    private function resolveSinkPath(): ?string
    {
        $sink = $this->cp->options['sink'] ?? null;
        if ($sink === true) {
            $path = tempnam(sys_get_temp_dir(), 'pulp-http-');
            if ($path === false) {
                throw new RuntimeException('Unable to create HTTP sink temp file');
            }

            return $path;
        }
        if (is_string($sink) && $sink !== '') {
            return $sink;
        }

        $guzzleSink = $this->cp->guzzleOptions['sink'] ?? null;

        return is_string($guzzleSink) && $guzzleSink !== '' ? $guzzleSink : null;
    }

    private function assertSuccessStatus(int $statusCode): void
    {
        $allowed = $this->cp->options['successStatuses'] ?? null;
        if (!is_array($allowed) || $allowed === []) {
            return;
        }

        $allowed = array_map('intval', $allowed);
        if (!in_array($statusCode, $allowed, true)) {
            throw new RuntimeException(
                'Http ' . $this->cp->method . ' on "' . $this->cp->uri . '" returned ' . $statusCode
            );
        }
    }
}

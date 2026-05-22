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
            $client = new Client();
            $res = $client->request(
                $this->cp->method,
                $this->cp->uri,
                $this->cp->guzzleOptions
            );
            $tmpFile = new File($this->cp->aliasFileName);
            $tmpFile->content = (string) $res->getBody();
            $file = $tmpFile;
        } catch (Throwable $err) {
            $err = new RuntimeException(
                'Http ' . $this->cp->method . ' on "' . $this->cp->uri . '" failed',
                0,
                $err
            );

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
}

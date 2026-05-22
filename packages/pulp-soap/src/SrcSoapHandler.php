<?php

declare(strict_types=1);

namespace OpenMapsight\pulpsoap;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use SoapClient;

class SrcSoapHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return [
            'fileName',
            'location',
            'request',
        ];
    }

    public function onEnd(): void
    {
        $options = [
            'trace' => 1,
            'location' => $this->cp->location,
            'uri' => $this->cp->location,
            'exceptions' => false,
        ];
        $client = new SoapClient(null, $options);
        $client->__soapCall('get', $this->cp->request);
        $file = new File($this->cp->fileName);
        $file->content = substr((string) $client->__getLastResponse(), 0);
        $this->pushFile($file);
    }
}

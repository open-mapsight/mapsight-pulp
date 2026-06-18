<?php

declare(strict_types=1);

use OpenMapsight\PulpSoap;
use OpenMapsight\pulpsoap\SrcSoapHandler;
use PHPUnit\Framework\TestCase;

class SrcSoapHandlerTest extends TestCase
{
    public function testFactoryCreatesSoapSourceHandler(): void
    {
        $handler = PulpSoap::srcSoap('response.xml', 'https://example.test/soap', [['request']]);

        $this->assertInstanceOf(SrcSoapHandler::class, $handler);
    }
}

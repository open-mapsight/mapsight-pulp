<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexenergy\dev\test;

use OpenMapsight\pulp\SrcHttpHandler;
use OpenMapsight\PulpDatexEnergy;
use OpenMapsight\pulpdatexenergy\MobilithekRequest;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use RuntimeException;

class MobilithekRequestTest extends TestCase
{
    public function testDefaultUrlAndP12CurlOptions(): void
    {
        $options = PulpDatexEnergy::mobilithekGuzzleOptions(
            'caller-subscription-id',
            '/tmp/client.p12',
            'caller-password',
            'Wed, 01 Jan 2025 00:00:00 GMT'
        );

        $this->assertSame(PulpDatexEnergy::SUBSCRIPTION_URL, MobilithekRequest::SUBSCRIPTION_URL);
        $this->assertSame('https://mobilithek.info:8443/mobilithek/api/v1.0/subscription', PulpDatexEnergy::SUBSCRIPTION_URL);
        $this->assertSame('gzip', $options['headers']['Accept-Encoding']);
        $this->assertSame('Wed, 01 Jan 2025 00:00:00 GMT', $options['headers']['If-Modified-Since']);
        $this->assertSame('caller-subscription-id', $options['query']['subscriptionID']);
        $this->assertSame('/tmp/client.p12', $options['curl'][CURLOPT_SSLCERT]);
        $this->assertSame('caller-password', $options['curl'][CURLOPT_SSLCERTPASSWD]);
        $this->assertSame('P12', $options['curl'][CURLOPT_SSLCERTTYPE]);
        $this->assertTrue($options['decode_content']);
        $this->assertFalse($options['http_errors']);
        $this->assertStringNotContainsString('1030105573172883456', json_encode($options, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('1030117702173069312', json_encode($options, JSON_THROW_ON_ERROR));
    }

    public function testSrcMobilithekReturnsConfiguredSrcHttpHandler(): void
    {
        $handler = PulpDatexEnergy::srcMobilithek(
            'sub-1',
            '/tmp/client.p12',
            'secret',
            null,
            'static.json'
        );

        $this->assertInstanceOf(SrcHttpHandler::class, $handler);

        $reflection = new ReflectionObject($handler);
        $cp = $reflection->getProperty('cp');
        $this->assertSame('GET', $cp->getValue($handler)->method);
        $this->assertSame(PulpDatexEnergy::SUBSCRIPTION_URL, $cp->getValue($handler)->uri);
        $this->assertSame('static.json', $cp->getValue($handler)->aliasFileName);
        $this->assertSame('P12', $cp->getValue($handler)->guzzleOptions['curl'][CURLOPT_SSLCERTTYPE]);
    }

    public function testRejectsMissingCertificateCredentials(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mobilithek client certificate path and password must be supplied');

        PulpDatexEnergy::mobilithekGuzzleOptions('sub-1', '', 'password');
    }
}

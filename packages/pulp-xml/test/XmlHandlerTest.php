<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpXML;
use PHPUnit\Framework\TestCase;

class XmlHandlerTest extends TestCase
{
    public function testXmlCanBeParsedAndQueriedByXPath(): void
    {
        $file = new File('data.xml');
        $file->content = '<root><item id="1">value</item></root>';

        $result = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpXML::parseSimpleXML())
            ->pipe(PulpXML::getXPathSingle('/root/item'))
            ->run();

        $this->assertInstanceOf(SimpleXMLElement::class, $result[0]->content);
        $this->assertSame('value', (string) $result[0]->content);
        $this->assertSame('1', (string) $result[0]->content['id']);
    }

    public function testXmlNamespacesCanBeStrippedFromStringContent(): void
    {
        $file = new File('data.xml');
        $file->content = '<ns:root xmlns:ns="https://example.test/ns" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><ns:item xsi:type="string">value</ns:item></ns:root>';

        $result = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpXML::stripXmlNamespaces())
            ->run();

        $this->assertStringNotContainsString('xmlns', $result[0]->content);
        $this->assertStringNotContainsString('ns:', $result[0]->content);
        $this->assertStringNotContainsString('xsi:type', $result[0]->content);

        $xml = simplexml_load_string($result[0]->content);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $this->assertSame('value', (string) $xml->item);
    }
}

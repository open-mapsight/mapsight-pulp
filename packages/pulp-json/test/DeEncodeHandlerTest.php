<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpJSON;
use PHPUnit\Framework\TestCase;

class DeEncodeHandlerTest extends TestCase
{
    private string $jsonStr;
    private array $jsonArr;

    public function setUp(): void
    {
        $jsonStr = '{
            "string": "aString1",
            "boolean": true,
            "int": 42,
            "float": 42.1337,
            "hash": {"string": "aString2"},
            "list": ["string1", 1337]
        }';
        $this->jsonStr = preg_replace('/\s+/', '', $jsonStr);

        $this->jsonArr = [
            'string' => 'aString1',
            'boolean' => true,
            'int' => 42,
            'float' => 42.1337,
            'hash' => [
                'string' => 'aString2',
            ],
            'list' => [
                'string1',
                1337,
            ],
        ];
    }

    public function test(): void
    {
        $f = new File('filename');
        $f->content = $this->jsonStr;

        Pulp::start()
            ->pipe(Pulp::src($f))
            ->pipe(Pulp::results(function ($res): void {
                $this->assertCount(1, $res);
                $this->assertSame($this->jsonStr, $res[0]->content);
            }))
            ->pipe(PulpJSON::decodeJSON())
            ->pipe(Pulp::results(function ($res): void {
                $this->assertCount(1, $res);
                $this->assertSame($this->jsonArr, $res[0]->content);
            }))
            ->pipe(PulpJSON::encodeJSON())
            ->pipe(Pulp::results(function ($res): void {
                $this->assertCount(1, $res);
                $this->assertSame($this->jsonStr, $res[0]->content);
            }))
            ->run();
    }

    public function testJsonp(): void
    {
        $f = new File('filename');
        $f->content = $this->jsonArr;

        Pulp::start()
            ->pipe(Pulp::src($f))
            ->pipe(PulpJSON::encodeJSONP('myJsonpFunction'))
            ->pipe(Pulp::results(function ($res): void {
                $this->assertCount(1, $res);
                $expected = 'myJsonpFunction(' . $this->jsonStr . ');';
                $this->assertSame($expected, $res[0]->content);
            }))
            ->run();
    }
}

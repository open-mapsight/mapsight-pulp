<?php

declare(strict_types=1);

namespace OpenMapsight\pulptic\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\PulpTIC;
use PHPUnit\Framework\TestCase;

class ParseHandlerTest extends TestCase
{
    public function testTic(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::srcFile(__DIR__ . '/files/input.xml', 'input.json'))
            ->pipe(PulpTIC::parse())
            ->pipe(Pulp::results(function ($res): void {
                file_put_contents(
                    __DIR__ . '/files/tmp/input.json',
                    json_encode(TestUtils::normalizeArray($res[0]->content), JSON_PRETTY_PRINT)
                );
            }))
            ->run();
        TestUtils::assertJsonSameFile('expected.parse.json', $res[0]->content);
    }

    public function testTic2(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::srcFile(__DIR__ . '/files/input-tic2.xml', 'input-tic2.json'))
            ->pipe(PulpTIC::parse())
            ->pipe(Pulp::results(function ($res): void {
                file_put_contents(
                    __DIR__ . '/files/tmp/input-tic2.json',
                    json_encode(TestUtils::normalizeArray($res[0]->content), JSON_PRETTY_PRINT)
                );
            }))
            ->run();
        TestUtils::assertJsonSameFile('expected-tic2.parse.json', $res[0]->content);
    }

    public function testTic3(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::srcFile(__DIR__ . '/files/input-tic3.xml', 'input-tic3.json'))
            ->pipe(PulpTIC::parse())
            ->pipe(Pulp::results(function ($res): void {
                file_put_contents(
                    __DIR__ . '/files/tmp/input-tic3.json',
                    json_encode(TestUtils::normalizeArray($res[0]->content), JSON_PRETTY_PRINT)
                );
            }))
            ->run();
        TestUtils::assertJsonSameFile('expected-tic3.parse.json', $res[0]->content);
    }
}

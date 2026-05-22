<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class PulpTest extends TestCase
{
    public function testGetLastHandler(): void
    {
        $h1 = Pulp::src('foobar');
        $h2 = Pulp::dest('foobaz');
        $p = Pulp::start()
            ->pipe($h1)
            ->pipe($h2);

        $this->assertSame($h2, $p->getLastHandler());
    }

    public function testDoNothing(): void
    {
        $f = new File('fileName');
        $f->content = 'fileContent';

        $res = Pulp::start()->run($f);

        $this->assertCount(1, $res);
        $this->assertSame($f, $res[0]);
    }

    public function testPulpAsHandler(): void
    {
        $f = new File('fileName');
        $f->content = 'fileContent';

        $fFiltered = new File('filter');
        $fFiltered->content = '';

        $inner = Pulp::start()
            ->pipe(Pulp::filter(static fn($f): bool => $f->fileName !== 'filter'))
            ->pipe(Pulp::map(static function ($f): object {
                $f = clone $f;
                $f->fileName .= '-mapName';
                $f->content .= '-mapContent';
                return $f;
            }));

        $res = Pulp::start()
            ->pipe($inner)
            ->run([
                $f,
                $fFiltered,
                $f,
                $f,
            ]);

        $this->assertCount(3, $res);
        foreach ($res as $file) {
            $this->assertSame('fileName-mapName', $file->fileName);
            $this->assertSame('fileContent-mapContent', $file->content);
        }
    }
}

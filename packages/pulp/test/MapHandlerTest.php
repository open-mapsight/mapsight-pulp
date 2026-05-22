<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class MapHandlerTest extends TestCase
{
    public function testMap(): void
    {
        $f1 = new File('fileName');
        $f1->content = 'file';

        $res = Pulp::start()
            ->pipe(Pulp::src($f1))
            ->pipe(Pulp::map(static function ($f) {
                $f->fileName .= '-mapName';
                $f->content .= '-mapContent';

                return $f;
            }))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('fileName-mapName', $res[0]->fileName);
        $this->assertSame('file-mapContent', $res[0]->content);
    }

    public function testMapNull(): void
    {
        $f1 = new File('fileName');
        $f1->content = 'file';

        $res = Pulp::start()
            ->pipe(Pulp::src($f1))
            ->pipe(Pulp::map(static fn(): null => null))
            ->run();

        $this->assertCount(0, $res);
    }
}

<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class MergeHandlerTest extends TestCase
{
    public function testMerge(): void
    {
        $f1 = new File('fileName1');
        $f1->content = 'file1';
        $p1 = Pulp::start()->pipe(Pulp::src($f1));

        $f2 = new File('fileName2');
        $f2->content = 'file2';
        $p2 = Pulp::start()->pipe(Pulp::src($f2));

        $res = Pulp::start()
            ->pipe(Pulp::merge($p1, $p2))
            ->run();

        $this->assertCount(2, $res);
        $this->assertSame('fileName1', $res[0]->fileName);
        $this->assertSame('file1', $res[0]->content);
        $this->assertSame('fileName2', $res[1]->fileName);
        $this->assertSame('file2', $res[1]->content);
    }
}

<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use PHPUnit\Framework\TestCase;

class SrcFileHandlerTest extends TestCase
{
    public function testWithoutAlias(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::srcFile(__FILE__))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame(__FILE__, $res[0]->srcFileName);
        $this->assertSame(__FILE__, $res[0]->fileName);
    }

    public function testWithAlias(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::srcFile(__FILE__, 'myalias.txt'))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame(__FILE__, $res[0]->srcFileName);
        $this->assertSame('myalias.txt', $res[0]->fileName);
    }
}

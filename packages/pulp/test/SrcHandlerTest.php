<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class SrcHandlerTest extends TestCase
{
    public function testSimple(): void
    {
        $fs = vfsStream::setup();

        $a = vfsStream::newDirectory('a')
            ->at($fs);

        $b = vfsStream::newDirectory('b')
            ->at($a);

        vfsStream::newFile('test.txt')
            ->withContent('fileContent')
            ->at($b);

        $res = Pulp::start()
            ->pipe(Pulp::src('.*\.txt', $fs->url()))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('fileContent', $res[0]->content);
    }
}

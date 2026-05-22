<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class DeleteHandlerTest extends TestCase
{
    public function testSimple(): void
    {
        $fs = vfsStream::setup(uniqid('', true));

        $dirA = vfsStream::newDirectory('a')->at($fs);
        $dirAB = vfsStream::newDirectory('b')->at($dirA);

        vfsStream::newFile('delete.txt')->at($fs);
        vfsStream::newFile('delete.txt')->at($dirA);
        vfsStream::newFile('a.txt')->at($dirA);
        vfsStream::newFile('delete.txt')->at($dirAB);
        vfsStream::newFile('a.txt')->at($dirAB);

        $res = Pulp::start()
            ->pipe(Pulp::src('.*delete.*', $fs->url()))
            ->pipe(Pulp::delete())
            ->run();

        $this->assertCount(3, $res);

        $this->assertFalse($fs->hasChild('delete.txt'));
        $this->assertFalse($fs->hasChild('a/delete.txt'));
        $this->assertTrue($fs->hasChild('a/a.txt'));
        $this->assertFalse($fs->hasChild('a/b/delete.txt'));
        $this->assertTrue($fs->hasChild('a/b/a.txt'));
    }
}

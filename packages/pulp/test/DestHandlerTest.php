<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class DestHandlerTest extends TestCase
{
    public function test(): void
    {
        $fs = vfsStream::setup();

        $f1 = new File('fileName.txt');
        $f1->content = 'fileContent';

        Pulp::start()
            ->pipe(Pulp::src($f1))
            ->pipe(Pulp::dest($fs->url()))
            ->run();

        $this->assertTrue($fs->hasChild('fileName.txt'));
        $this->assertSame(
            'fileContent',
            $fs->getChild('fileName.txt')->getContent()
        );
    }

    public function testNestedDirs(): void
    {
        $fs = vfsStream::setup();

        $f1 = new File('a/b/fileName.txt');
        $f1->content = 'fileContent';

        Pulp::start()
            ->pipe(Pulp::src($f1))
            ->pipe(Pulp::dest($fs->url()))
            ->run();

        $this->assertTrue($fs->hasChild('a/b/fileName.txt'));
        $this->assertSame(
            'fileContent',
            $fs->getChild('a/b/fileName.txt')->getContent()
        );
    }
}

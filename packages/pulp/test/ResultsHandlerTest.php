<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class ResultsHandlerTest extends TestCase
{
    public function testResults(): void
    {
        $files = [
            new File('fileName1'),
            new File('fileName2'),
            new File('fileName3'),
        ];

        $files[0]->content = 'file1';
        $files[1]->content = 'file2';
        $files[2]->content = 'file3';

        $res2 = null;
        $res = Pulp::start()
            ->pipe(Pulp::src($files))
            ->pipe(Pulp::results(static function ($res2_) use (&$res2): void {
                $res2 = $res2_;
            }))
            ->run();

        $this->assertCount(3, $res);

        $this->assertSame('fileName1', $res[0]->fileName);
        $this->assertSame('fileName2', $res[1]->fileName);
        $this->assertSame('fileName3', $res[2]->fileName);

        $this->assertSame('file1', $res[0]->content);
        $this->assertSame('file2', $res[1]->content);
        $this->assertSame('file3', $res[2]->content);

        $this->assertEquals($res, $res2);
    }

    public function testWithChanges(): void
    {
        $f = new File('fileName');
        $f->content = 'fileContent';

        Pulp::start()
            ->pipe(Pulp::src($f))
            ->pipe(Pulp::results(function ($res): void {
                $this->assertCount(1, $res);
                $this->assertSame('fileContent', $res[0]->content);
            }))
            ->pipe(Pulp::map(static function ($f) {
                $f->content = 'newFileContent';

                return $f;
            }))
            ->pipe(Pulp::results(function ($res): void {
                $this->assertCount(1, $res);
                $this->assertSame('newFileContent', $res[0]->content);
            }))
            ->run();
    }
}

<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class SplitHandlerTest extends TestCase
{
    public function testSplitFeedsFilesIntoBranchesAndMergesResults(): void
    {
        $file = new File('file.txt');
        $file->content = 'content';

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(Pulp::split(
                static fn(Pulp $p): Pulp => $p
                    ->pipe(Pulp::map(static function (File $file): File {
                        $file->fileName = 'a-' . $file->fileName;
                        $file->content .= '-a';

                        return $file;
                    })),
                static fn(Pulp $p): Pulp => $p
                    ->pipe(Pulp::map(static function (File $file): File {
                        $file->fileName = 'b-' . $file->fileName;
                        $file->content .= '-b';

                        return $file;
                    }))
            ))
            ->run();

        $this->assertCount(2, $res);
        $this->assertSame('a-file.txt', $res[0]->fileName);
        $this->assertSame('content-a', $res[0]->content);
        $this->assertSame('b-file.txt', $res[1]->fileName);
        $this->assertSame('content-b', $res[1]->content);
    }

    public function testSplitAcceptsArrayOfBranches(): void
    {
        $file = new File('file.txt');
        $file->content = 'content';

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(Pulp::split([
                Pulp::start()->pipe(Pulp::map(static fn(File $file): File => $file)),
                static fn(Pulp $p): Pulp => $p,
            ]))
            ->run();

        $this->assertCount(2, $res);
        $this->assertSame('file.txt', $res[0]->fileName);
        $this->assertSame('content', $res[0]->content);
        $this->assertSame('file.txt', $res[1]->fileName);
        $this->assertSame('content', $res[1]->content);
    }
}

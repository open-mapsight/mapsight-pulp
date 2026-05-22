<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class FileSwitchHandlerTest extends TestCase
{
    public function test(): void
    {
        vfsStream::setup();

        $f1 = new File('fileName1.txt');
        $f1->content = 'fileContent1';

        $f2 = new File('fileName2.txt');
        $f2->content = 'fileContent2';

        $res = Pulp::start()
            ->pipe(Pulp::src([$f1, $f2]))
            ->pipe(Pulp::fileSwitch(
                [
                    'fileName1\.txt' => static fn(Pulp $p): Pulp => $p
                        ->pipe(Pulp::map(static function (File $f): File {
                            $f->content .= 'Mapped';

                            return $f;
                        })),
                ],
                static fn(Pulp $p): Pulp => $p
                    ->pipe(Pulp::map(static function (File $f): File {
                        $f->content .= 'Default';

                        return $f;
                    }))
            ))
            ->run();

        $this->assertCount(2, $res);

        $this->assertSame('fileName1.txt', $res[0]->fileName);
        $this->assertSame('fileContent1Mapped', $res[0]->content);

        $this->assertSame('fileName2.txt', $res[1]->fileName);
        $this->assertSame('fileContent2Default', $res[1]->content);
    }

    public function testWithDefaultWithoutHandler(): void
    {
        vfsStream::setup();

        $f1 = new File('fileName1.txt');
        $f1->content = 'fileContent1';

        $f2 = new File('fileName2.txt');
        $f2->content = 'fileContent2';

        $res = Pulp::start()
            ->pipe(Pulp::src([$f1, $f2]))
            ->pipe(Pulp::fileSwitch([
                'fileName1\.txt' => static fn(Pulp $p): Pulp => $p
                    ->pipe(Pulp::map(static function (File $f): File {
                        $f->content .= 'Mapped';

                        return $f;
                    })),
            ]))
            ->run();

        $this->assertCount(2, $res);

        $this->assertSame('fileName1.txt', $res[0]->fileName);
        $this->assertSame('fileContent1Mapped', $res[0]->content);

        $this->assertSame('fileName2.txt', $res[1]->fileName);
        $this->assertSame('fileContent2', $res[1]->content);
    }
}

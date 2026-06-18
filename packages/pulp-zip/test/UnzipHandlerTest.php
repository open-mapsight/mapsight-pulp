<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\PulpZip;
use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class UnzipHandlerTest extends TestCase
{
    public function testUnzipEmitsArchiveEntriesAsFiles(): void
    {
        $zipFile = $this->createZipFile([
            'a.txt' => 'A',
            'nested/b.txt' => 'B',
        ]);

        $res = Pulp::start()
            ->pipe(Pulp::src($zipFile))
            ->pipe(PulpZip::unzip())
            ->run();

        $this->assertCount(2, $res);
        $this->assertSame('a.txt', $res[0]->fileName);
        $this->assertSame('archive.zip#a.txt', $res[0]->srcFileName);
        $this->assertSame('A', $res[0]->content);
        $this->assertSame('nested/b.txt', $res[1]->fileName);
        $this->assertSame('archive.zip#nested/b.txt', $res[1]->srcFileName);
        $this->assertSame('B', $res[1]->content);
    }

    public function testUnzipFiltersEntries(): void
    {
        $zipFile = $this->createZipFile([
            'a.csv' => 'A',
            'b.txt' => 'B',
        ]);

        $res = Pulp::start()
            ->pipe(Pulp::src($zipFile))
            ->pipe(PulpZip::unzip('.*\.csv'))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('a.csv', $res[0]->fileName);
        $this->assertSame('A', $res[0]->content);
    }

    private function createZipFile(array $entries): File
    {
        $inputFiles = [];
        foreach ($entries as $fileName => $content) {
            $file = new File($fileName);
            $file->content = $content;
            $inputFiles[] = $file;
        }

        $zipFiles = Pulp::start()
            ->pipe(Pulp::src($inputFiles))
            ->pipe(PulpZip::zip('archive.zip'))
            ->run();

        return $zipFiles[0];
    }
}

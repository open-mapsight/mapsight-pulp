<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\PulpZip;
use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class ZipHandlerTest extends TestCase
{
    public function testZipCreatesArchiveFile(): void
    {
        $f1 = new File('a.txt');
        $f1->content = 'A';

        $f2 = new File('nested/b.txt');
        $f2->content = 'B';

        $res = Pulp::start()
            ->pipe(Pulp::src([$f1, $f2]))
            ->pipe(PulpZip::zip('bundle.zip'))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('bundle.zip', $res[0]->fileName);

        $this->assertZipEntryContent($res[0]->content, 'a.txt', 'A');
        $this->assertZipEntryContent($res[0]->content, 'nested/b.txt', 'B');
    }

    private function assertZipEntryContent(string $zipContent, string $entryName, string $expectedContent): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pulp-zip-test-');
        if ($tmpFile === false || file_put_contents($tmpFile, $zipContent) === false) {
            throw new RuntimeException('Unable to create temporary test ZIP file');
        }

        $zip = new ZipArchive();
        $isOpen = false;
        try {
            $this->assertTrue($zip->open($tmpFile));
            $isOpen = true;
            $this->assertSame($expectedContent, $zip->getFromName($entryName));
        } finally {
            if ($isOpen) {
                $zip->close();
            }
            @unlink($tmpFile);
        }
    }
}

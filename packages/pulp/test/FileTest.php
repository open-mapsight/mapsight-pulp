<?php

declare(strict_types=1);

use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{
    public function testGetterNull(): void
    {
        $f = new File('fileName');
        $f->content = 'fileContent';

        $this->assertSame('fileName', $f->fileName);
        $this->assertSame('fileContent', $f->content);
        $this->assertNull($f->nonexistent);
    }

    public function testIsset(): void
    {
        $f = new File('fileName');
        $f->content = 'fileContent';
        $f->myProperty = 'foobar';

        $this->assertTrue(isset($f->myProperty));
        $this->assertTrue(isset($f->content));
        $this->assertFalse(isset($f->nonexistent));
    }

    public function testUnset(): void
    {
        $f = new File('fileName');
        $f->content = 'fileContent';
        $f->myProperty = 'foobar';

        $this->assertTrue(isset($f->myProperty));
        unset($f->myProperty);
        $this->assertFalse(isset($f->myProperty));
        $this->assertNull($f->myProperty);
        $this->assertFalse(isset($f->myProperty));
    }

    public function testFromPathLoadsContentLazily(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pulp-file-test-');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, 'originalContent');

        try {
            $f = File::fromPath($tmpFile, 'file.txt');
            file_put_contents($tmpFile, 'updatedContent');

            $this->assertSame('updatedContent', $f->content);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testStreamReadsFromPathWithoutTouchingContent(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pulp-file-test-');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, 'streamContent');

        try {
            $f = File::fromPath($tmpFile, 'file.txt');
            $stream = $f->stream();

            try {
                $this->assertSame('streamContent', stream_get_contents($stream));
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testStreamFromGeneratedContent(): void
    {
        $f = new File('file.txt');
        $f->content = 'generatedContent';

        $stream = $f->stream();

        try {
            $this->assertSame('generatedContent', stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }

    public function testStreamRejectsNonStringContent(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to create stream from non-string file content');

        $f = new File('file.txt');
        $f->content = ['not streamable'];
        $f->stream();
    }
}

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
}

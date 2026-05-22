<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class AbstractHandlerTest extends TestCase
{
    public function testNullHandler(): void
    {
        $f1 = new File('fileName');
        $f1->content = 'fileContent';

        $res = Pulp::start()
            ->pipe(Pulp::src($f1))
            ->pipe(new AbstractHandlerTestNullHandler())
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('fileName', $res[0]->fileName);
        $this->assertSame('fileContent', $res[0]->content);
    }
}

class AbstractHandlerTestNullHandler extends AbstractHandler {}

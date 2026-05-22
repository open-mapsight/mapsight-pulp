<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class DebugHandlerTest extends TestCase
{
    public function testEmpty(): void
    {
        $f1 = new File('fileName');
        $f1->content = 'content';

        Pulp::start()
            ->pipe(Pulp::src($f1))
            ->pipe(Pulp::debug(0))
            ->run();

        $this->expectOutputString('"fileName"' . "\n");
    }

    public function testShort(): void
    {
        $f1 = new File('fileName');
        $f1->content = 'content';

        Pulp::start()
            ->pipe(Pulp::src($f1))
            ->pipe(Pulp::debug(3))
            ->run();

        $this->expectOutputString('"fileName":(string) con[...]' . "\n");
    }

    public function testLong(): void
    {
        $f1 = new File('fileName');
        $f1->content = 'content';

        Pulp::start()
            ->pipe(Pulp::src($f1))
            ->pipe(Pulp::debug())
            ->run();

        $this->expectOutputString('"fileName":(string) content' . "\n");
    }
}

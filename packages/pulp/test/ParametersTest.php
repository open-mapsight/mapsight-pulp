<?php

declare(strict_types=1);

use OpenMapsight\pulp\Parameters;
use PHPUnit\Framework\TestCase;

class ParametersTest extends TestCase
{
    public function testUnset(): void
    {
        $p = new Parameters(['testParam']);
        $p->bindParameters(['testVal']);
        $this->assertTrue(isset($p->testParam));
        unset($p->testParam);
        $this->assertFalse(isset($p->testParam));
    }

    public function testSet(): void
    {
        $p = new Parameters(['testParam']);
        $p->bindParameters(['testVal']);
        $this->assertSame('testVal', $p->testParam);
        $p->testParam = 'newVal';
        $this->assertSame('newVal', $p->testParam);
    }

    public function testHandlerMergeList(): void
    {
        $p = new Parameters([
            [
                'key' => 'testParam',
                'handler' => 'merge',
                'default' => ['defVal1', 'defVal2'],
            ],
        ]);
        $p->bindParameters([
            ['testVal1', 'testVal2'],
        ]);

        $this->assertSame(
            ['defVal1', 'defVal2', 'testVal1', 'testVal2'],
            $p->testParam
        );
    }

    public function testHandlerMergeHash(): void
    {
        $p = new Parameters([
            [
                'key' => 'testParam',
                'handler' => 'merge',
                'default' => [
                    'defKey1' => 'defVal1',
                    'defKey2' => 'defVal2',
                ],
            ],
        ]);
        $p->bindParameters([
            [
                'newKey1' => 'testVal1',
                'newKey2' => 'testVal2',
                'defKey2' => 'testVal3',
            ],
        ]);

        $this->assertSame(
            [
                'defKey1' => 'defVal1',
                'defKey2' => 'testVal3',
                'newKey1' => 'testVal1',
                'newKey2' => 'testVal2',
            ],
            $p->testParam
        );
    }

    public function testHandlerFunction(): void
    {
        $p = new Parameters([
            [
                'key' => 'testParam',
                'handler' => static fn($param, $def): string => 'param:' . $param . ';def:' . $def['default'],
                'default' => 'defVal',
            ],
        ]);
        $p->bindParameters(['paramVal']);

        $this->assertSame('param:paramVal;def:defVal', $p->testParam);
    }
}

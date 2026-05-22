<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class ShadowHandlerTest extends TestCase
{
    public function test(): void
    {
        $f1 = new File('fileName');
        $f1->content = ['start'];

        Pulp::start()
            ->pipe(Pulp::src($f1))
            ->pipe(Pulp::shadow(fn($p) => $p
                ->pipe(Pulp::map(static function ($f2) {
                    $f2->content[] = 'shadow';

                    return $f2;
                }))
                ->pipe(Pulp::results(function ($res2): void {
                    $this->assertCount(1, $res2);
                    $this->assertSame(['start', 'shadow'], $res2[0]->content);
                }))))
            ->pipe(Pulp::map(static function ($f3) {
                $f3->content[] = 'main';

                return $f3;
            }))
            ->pipe(Pulp::results(function ($res3): void {
                $this->assertCount(1, $res3);
                $this->assertSame(['start', 'main'], $res3[0]->content);
            }))
            ->run();
    }

    public function testEmpty(): void
    {
        $f1 = new File('fileName');
        $f1->content = ['start'];

        Pulp::start()
            ->pipe(Pulp::src($f1))
            ->pipe(Pulp::shadow(static fn($p) => $p))
            ->pipe(Pulp::results(function ($res): void {
                $this->assertCount(1, $res);
                $this->assertSame(['start'], $res[0]->content);
            }))
            ->run();
    }
}

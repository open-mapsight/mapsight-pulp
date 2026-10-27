<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class EachHandlerTest extends TestCase
{
    private static function file(string $fileName, string $content): File
    {
        $file = new File($fileName);
        $file->content = $content;

        return $file;
    }

    public function testRunsAPipelinePerFileAndPushesItsResults(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src([self::file('a.url', 'first'), self::file('b.url', 'second')]))
            ->pipe(Pulp::each(static fn(File $request): Pulp => Pulp::start()
                ->pipe(Pulp::src([
                    self::file($request->fileName . '.1', strtoupper($request->content)),
                    self::file($request->fileName . '.2', $request->content),
                ]))))
            ->run();

        $this->assertSame(['a.url.1', 'a.url.2', 'b.url.1', 'b.url.2'], array_map(static fn(File $f): string => $f->fileName, $res));
        $this->assertSame('FIRST', $res[0]->content);
    }

    public function testThePipelineCanWorkOnTheFileItself(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src([self::file('a.txt', 'a')]))
            ->pipe(Pulp::each(static fn(File $file): Pulp => Pulp::start()
                ->pipe(Pulp::src($file))
                ->pipe(Pulp::map(static function (File $f): File {
                    $f->content .= '!';

                    return $f;
                }))))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('a!', $res[0]->content);
    }

    public function testThePipelineStartsWithoutTheFile(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src([self::file('a.txt', 'a')]))
            ->pipe(Pulp::each(static fn(File $file): Pulp => Pulp::start()
                ->pipe(Pulp::map(static fn(File $f): File => $f))))
            ->run();

        $this->assertSame([], $res);
    }

    public function testAFailingPipelineFailsTheRun(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Pipeline for file "a.url" failed');

        Pulp::start()
            ->pipe(Pulp::src([self::file('a.url', 'a')]))
            ->pipe(Pulp::each(static function (File $file): Pulp {
                throw new RuntimeException('unavailable');
            }))
            ->run();
    }

    public function testSkipExceptionsContinuesWithTheNextFile(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src([self::file('bad', ''), self::file('good', 'ok')]))
            ->pipe(Pulp::each(static function (File $file): Pulp {
                if ($file->fileName === 'bad') {
                    throw new RuntimeException('unavailable');
                }

                return Pulp::start()->pipe(Pulp::src($file));
            }, ['skipExceptions' => true, 'logSkipExceptions' => false]))
            ->run();

        $this->assertSame(['good'], array_map(static fn(File $f): string => $f->fileName, $res));
    }

    public function testTheCallbackMustReturnAPipeline(): void
    {
        $this->expectException(RuntimeException::class);

        Pulp::start()
            ->pipe(Pulp::src([self::file('a', '')]))
            ->pipe(Pulp::each(static fn(File $file): string => 'no pipeline'))
            ->run();
    }
}

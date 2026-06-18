<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use PHPUnit\Framework\TestCase;

class ReadmeUppercaseHandler extends AbstractHandler
{
    public function onFile(File $file): void
    {
        $file->content = strtoupper($file->content);
        $this->pushFile($file);
    }
}

class ReadmePrefixHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['prefix'];
    }

    public function onFile(File $file): void
    {
        $file->content = $this->cp->prefix . $file->content;
        $this->pushFile($file);
    }
}

class ReadmeExamplesTest extends TestCase
{
    private string $originalCwd;
    private array $tmpDirs = [];

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        foreach ($this->tmpDirs as $tmpDir) {
            $this->removeDirectory($tmpDir);
        }

        $this->tmpDirs = [];
    }

    public function testQuickStartExample(): void
    {
        $workspace = $this->createWorkspace();
        chdir($workspace);
        mkdir('input');
        mkdir('output');
        file_put_contents('input/example.txt', 'hello');

        Pulp::start()
            ->pipe(Pulp::src('.*\.txt', 'input'))
            ->pipe(Pulp::map(static function (File $file): File {
                $file->content = strtoupper($file->content);

                return $file;
            }))
            ->pipe(Pulp::dest('output'))
            ->run();

        $this->assertSame('HELLO', file_get_contents('output/example.txt'));
    }

    public function testRunWithDirectFileExample(): void
    {
        $file = new File('example.txt');
        $file->content = 'Hello';

        $results = Pulp::start()
            ->pipe(Pulp::map(static fn(File $file): File => $file))
            ->run($file);

        $this->assertCount(1, $results);
        $this->assertSame('Hello', $results[0]->content);
    }

    public function testStartExample(): void
    {
        $pulp = Pulp::start();

        $this->assertInstanceOf(Pulp::class, $pulp);
    }

    public function testSrcExample(): void
    {
        $workspace = $this->createWorkspace();
        chdir($workspace);
        mkdir('data');
        file_put_contents('data/a.csv', 'a');
        file_put_contents('data/b.txt', 'b');

        $results = Pulp::start()
            ->pipe(Pulp::src('.*\.csv', 'data'))
            ->run();

        $this->assertCount(1, $results);
        $this->assertSame('a.csv', $results[0]->fileName);
        $this->assertSame('a', $results[0]->content);
    }

    public function testDestExample(): void
    {
        $workspace = $this->createWorkspace();
        chdir($workspace);
        mkdir('result');

        $file = new File('example.txt');
        $file->content = 'content';

        Pulp::start()
            ->pipe(Pulp::dest('result'))
            ->run($file);

        $this->assertSame('content', file_get_contents('result/example.txt'));
    }

    public function testMapExample(): void
    {
        $skip = new File('skip.txt');
        $skip->content = 'skip';

        $keep = new File('keep.txt');
        $keep->content = 'keep';

        $results = Pulp::start()
            ->pipe(Pulp::map(static function (File $file): ?File {
                if ($file->fileName === 'skip.txt') {
                    return null;
                }

                $file->content .= "\n";

                return $file;
            }))
            ->run([$skip, $keep]);

        $this->assertCount(1, $results);
        $this->assertSame("keep\n", $results[0]->content);
    }

    public function testFilterExample(): void
    {
        $json = new File('example.json');
        $json->content = '{}';

        $txt = new File('example.txt');
        $txt->content = 'text';

        $results = Pulp::start()
            ->pipe(Pulp::filter(static fn(File $file): bool => str_ends_with($file->fileName, '.json')))
            ->run([$json, $txt]);

        $this->assertCount(1, $results);
        $this->assertSame('example.json', $results[0]->fileName);
    }

    public function testResultsExample(): void
    {
        $file = new File('example.txt');
        $file->content = 'content';
        $seen = null;

        Pulp::start()
            ->pipe(Pulp::results(static function (array $files) use (&$seen): void {
                $seen = $files;
            }))
            ->run($file);

        $this->assertCount(1, $seen);
        $this->assertSame('content', $seen[0]->content);
    }

    public function testSplitExample(): void
    {
        $workspace = $this->createWorkspace();
        chdir($workspace);
        mkdir('input');
        mkdir('result');
        file_put_contents('input/example.txt', 'MiXeD');

        Pulp::start()
            ->pipe(Pulp::src('.*\.txt', 'input'))
            ->pipe(Pulp::split(
                static fn(Pulp $p): Pulp => $p->pipe(Pulp::map(static function (File $file): File {
                    $file->fileName = 'upper-' . $file->fileName;
                    $file->content = strtoupper($file->content);

                    return $file;
                })),
                static fn(Pulp $p): Pulp => $p->pipe(Pulp::map(static function (File $file): File {
                    $file->fileName = 'lower-' . $file->fileName;
                    $file->content = strtolower($file->content);

                    return $file;
                })),
            ))
            ->pipe(Pulp::dest('result'))
            ->run();

        $this->assertSame('MIXED', file_get_contents('result/upper-example.txt'));
        $this->assertSame('mixed', file_get_contents('result/lower-example.txt'));
    }

    public function testMergeExample(): void
    {
        $workspace = $this->createWorkspace();
        chdir($workspace);
        mkdir('a');
        mkdir('b');
        mkdir('result');
        file_put_contents('a/a.json', 'a');
        file_put_contents('b/b.json', 'b');

        $a = Pulp::start()->pipe(Pulp::src('.*\.json', 'a'));
        $b = Pulp::start()->pipe(Pulp::src('.*\.json', 'b'));

        Pulp::start()
            ->pipe(Pulp::merge($a, $b))
            ->pipe(Pulp::dest('result'))
            ->run();

        $this->assertSame('a', file_get_contents('result/a.json'));
        $this->assertSame('b', file_get_contents('result/b.json'));
    }

    public function testShadowExample(): void
    {
        $file = new File('example.txt');
        $file->content = 'abcdef';

        ob_start();
        try {
            $results = Pulp::start()
                ->pipe(Pulp::shadow(static fn(Pulp $p): Pulp => $p
                    ->pipe(Pulp::debug())
                ))
                ->run($file);

            $output = ob_get_clean();
        } catch (Throwable $err) {
            ob_end_clean();
            throw $err;
        }

        $this->assertSame('abcdef', $results[0]->content);
        $this->assertStringContainsString('example.txt', $output);
    }

    public function testFileSwitchExample(): void
    {
        $json = new File('example.json');
        $json->content = 'json';

        $xml = new File('example.xml');
        $xml->content = 'xml';

        $other = new File('example.txt');
        $other->content = 'other';

        $results = Pulp::start()
            ->pipe(Pulp::fileSwitch([
                '.*\.json' => static fn(Pulp $p): Pulp => $p->pipe(Pulp::map(static function (File $file): File {
                    $file->content .= '-json';

                    return $file;
                })),
                '.*\.xml' => static fn(Pulp $p): Pulp => $p->pipe(Pulp::map(static function (File $file): File {
                    $file->content .= '-xml';

                    return $file;
                })),
            ], static fn(Pulp $p): Pulp => $p))
            ->run([$json, $xml, $other]);

        $this->assertSame('json-json', $results[0]->content);
        $this->assertSame('xml-xml', $results[1]->content);
        $this->assertSame('other', $results[2]->content);
    }

    public function testReadTransformWritePattern(): void
    {
        $workspace = $this->createWorkspace();
        chdir($workspace);
        mkdir('input');
        mkdir('output');
        file_put_contents('input/example.txt', " content \n");

        Pulp::start()
            ->pipe(Pulp::src('.*\.txt', 'input'))
            ->pipe(Pulp::map(static function (File $file): File {
                $file->content = trim($file->content) . "\n";

                return $file;
            }))
            ->pipe(Pulp::dest('output'))
            ->run();

        $this->assertSame("content\n", file_get_contents('output/example.txt'));
    }

    public function testProcessLargeFilesWithStreamsPattern(): void
    {
        $file = new File('example.txt');
        $file->content = "a\nb\n";

        $results = Pulp::start()
            ->pipe(Pulp::map(static function (File $file): File {
                $stream = $file->stream();
                $lineCount = 0;

                try {
                    while (($line = fgets($stream)) !== false) {
                        $lineCount++;
                    }
                } finally {
                    fclose($stream);
                }

                $file->lineCount = $lineCount;

                return $file;
            }))
            ->run($file);

        $this->assertSame(2, $results[0]->lineCount);
    }

    public function testCustomHandlerExample(): void
    {
        $file = new File('example.txt');
        $file->content = 'hello';

        $results = Pulp::start()
            ->pipe(new ReadmeUppercaseHandler())
            ->run($file);

        $this->assertSame('HELLO', $results[0]->content);
    }

    public function testConstructorParamHandlerExample(): void
    {
        $file = new File('example.txt');
        $file->content = 'content';

        $results = Pulp::start()
            ->pipe(new ReadmePrefixHandler('prefix-'))
            ->run($file);

        $this->assertSame('prefix-content', $results[0]->content);
    }

    private function createWorkspace(): string
    {
        $tmpDir = sys_get_temp_dir() . '/pulp-readme-test-' . uniqid('', true);
        mkdir($tmpDir);
        $this->tmpDirs[] = $tmpDir;

        return $tmpDir;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($directory);
    }
}

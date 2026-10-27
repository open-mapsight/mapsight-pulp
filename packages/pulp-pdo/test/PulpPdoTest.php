<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpPdo;
use PHPUnit\Framework\TestCase;

class PulpPdoTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE places (source TEXT NOT NULL, id TEXT NOT NULL, name TEXT, tags TEXT, open INTEGER, PRIMARY KEY (source, id))');
    }

    private static function rows(string $fileName, array $rows): File
    {
        $file = new File($fileName);
        $file->content = $rows;

        return $file;
    }

    private function stored(): array
    {
        return $this->pdo->query('SELECT source, id, name, tags, open FROM places ORDER BY source, id')->fetchAll(PDO::FETCH_NUM);
    }

    public function testWriteInsertsRowsInChunksAndPassesFilesOn(): void
    {
        $rows = array_map(static fn(int $i): array => ['source' => 'a', 'id' => (string) $i, 'name' => 'Ort ' . $i, 'tags' => ['x', 'ü'], 'open' => $i % 2 === 0], range(1, 5));

        $res = Pulp::start()
            ->pipe(Pulp::src(self::rows('a.json', $rows)))
            ->pipe(PulpPdo::write($this->pdo, 'places', ['chunkSize' => 2]))
            ->run();

        $this->assertSame(['a.json'], array_map(static fn(File $f): string => $f->fileName, $res));
        $this->assertCount(5, $this->stored());
        $this->assertSame(['a', '2', 'Ort 2', '["x","ü"]', 1], $this->stored()[1]);
    }

    public function testReplaceDeletesTheMatchingRowsFirst(): void
    {
        $this->pdo->exec("INSERT INTO places (source, id, name) VALUES ('a', 'old', 'Alt'), ('b', '1', 'Bleibt')");

        Pulp::start()
            ->pipe(Pulp::src(self::rows('a', [['source' => 'a', 'id' => 'new', 'name' => 'Neu']])))
            ->pipe(PulpPdo::write($this->pdo, 'places', ['replace' => static fn(File $file): array => ['source' => $file->fileName]]))
            ->run();

        $this->assertSame([['a', 'new', 'Neu', null, null], ['b', '1', 'Bleibt', null, null]], $this->stored());

        Pulp::start()
            ->pipe(Pulp::src(self::rows('c', [['source' => 'c', 'id' => '1', 'name' => 'Allein']])))
            ->pipe(PulpPdo::write($this->pdo, 'places', ['replace' => true]))
            ->run();

        $this->assertSame([['c', '1', 'Allein', null, null]], $this->stored());
    }

    public function testAFailingFileIsRolledBack(): void
    {
        $this->pdo->exec("INSERT INTO places (source, id, name) VALUES ('a', '1', 'Vorher')");
        $duplicate = [['source' => 'a', 'id' => '2', 'name' => 'x'], ['source' => 'a', 'id' => '2', 'name' => 'y']];

        try {
            Pulp::start()
                ->pipe(Pulp::src(self::rows('a', $duplicate)))
                ->pipe(PulpPdo::write($this->pdo, 'places', ['replace' => true, 'chunkSize' => 1]))
                ->run();
            $this->fail('Expected the duplicate key to fail');
        } catch (RuntimeException $err) {
            $this->assertStringContainsString('Writing file "a" to the database failed', $err->getMessage());
        }

        $this->assertSame([['a', '1', 'Vorher', null, null]], $this->stored());
        $this->assertFalse($this->pdo->inTransaction());
    }

    public function testSkipExceptionsContinuesWithTheNextFile(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src([
                self::rows('bad', [['source' => 'a', 'unknown' => 1]]),
                self::rows('good', [['source' => 'b', 'id' => '1', 'name' => 'Gut']]),
            ]))
            ->pipe(PulpPdo::write($this->pdo, 'places', ['skipExceptions' => true, 'logSkipExceptions' => false]))
            ->run();

        $this->assertCount(2, $res);
        $this->assertSame([['b', '1', 'Gut', null, null]], $this->stored());
    }

    public function testColumnsMayComeInAnyOrder(): void
    {
        Pulp::start()
            ->pipe(Pulp::src(self::rows('a', [['source' => 'a', 'id' => '1', 'name' => 'Eins'], ['name' => 'Zwei', 'id' => '2', 'source' => 'a']])))
            ->pipe(PulpPdo::write($this->pdo, 'places'))
            ->run();

        $this->assertSame([['a', '1', 'Eins', null, null], ['a', '2', 'Zwei', null, null]], $this->stored());
    }

    public function testContentThatDoesNotFitFailsTheFile(): void
    {
        foreach ([
            'Row 1 has other columns than row 0 (missing: name; extra: tags)' => ['places', [['source' => 'a', 'id' => '1', 'name' => 'x'], ['source' => 'a', 'id' => '2', 'tags' => []]]],
            'Row 0 is not an array of column names to values' => ['places', [['a', '1']]],
            'Expected the file content to be a list of rows' => ['places', ['not' => 'a list']],
            'Invalid identifier "places; DROP TABLE places"' => ['places; DROP TABLE places', [['source' => 'a', 'id' => '1']]],
            'Invalid identifier "name""' => ['places', [['source' => 'a', 'id' => '1', 'name"' => 'x']]],
        ] as $message => [$table, $rows]) {
            try {
                Pulp::start()->pipe(Pulp::src(self::rows('x', $rows)))->pipe(PulpPdo::write($this->pdo, $table))->run();
                $this->fail('Expected ' . json_encode([$table, $rows]) . ' to be rejected');
            } catch (RuntimeException $err) {
                $this->assertSame($message, $err->getPrevious()?->getMessage());
                $this->assertSame([], $this->stored());
            }
        }
    }

    public function testTableAndConnectionMayBeCallbacks(): void
    {
        $opened = 0;
        $connect = function () use (&$opened): PDO {
            $opened++;

            return $this->pdo;
        };

        Pulp::start()
            ->pipe(Pulp::src([self::rows('places', [['source' => 'a', 'id' => '1']]), self::rows('places', [['source' => 'a', 'id' => '2']])]))
            ->pipe(PulpPdo::write($connect, static fn(File $file): string => $file->fileName))
            ->run();

        $this->assertSame(1, $opened);
        $this->assertCount(2, $this->stored());
    }

    public function testSetUpRunsOnceBeforeTheFirstWriteOutsideTheTransaction(): void
    {
        $calls = [];
        $setUp = static function (PDO $pdo) use (&$calls): void {
            $calls[] = $pdo->inTransaction();
            $pdo->exec('CREATE TABLE IF NOT EXISTS stops (id TEXT)');
        };

        Pulp::start()
            ->pipe(Pulp::src([self::rows('a', [['id' => '1']]), self::rows('b', [['id' => '2']])]))
            ->pipe(PulpPdo::write($this->pdo, 'stops', ['setUp' => $setUp]))
            ->run();

        $this->assertSame([false], $calls);
        $this->assertSame(['1', '2'], $this->pdo->query('SELECT id FROM stops ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testQueryEmitsTheRowsAfterTheIncomingFiles(): void
    {
        $this->pdo->exec("INSERT INTO places (source, id, name) VALUES ('a', '1', 'Eins'), ('a', '2', 'Zwei'), ('b', '1', 'Drei')");

        $res = Pulp::start()
            ->pipe(Pulp::src(self::rows('before', [])))
            ->pipe(PulpPdo::query($this->pdo, 'SELECT id, name FROM places WHERE source = ? ORDER BY id', ['a'], 'a.json'))
            ->run();

        $this->assertSame(['before', 'a.json'], array_map(static fn(File $f): string => $f->fileName, $res));
        $this->assertSame([['id' => '1', 'name' => 'Eins'], ['id' => '2', 'name' => 'Zwei']], $res[1]->content);
    }

    public function testTransactionCommitsOrRollsBackEachFile(): void
    {
        $write = static function (PDO $pdo, File $file): void {
            $pdo->prepare('INSERT INTO places (source, id) VALUES (?, ?)')->execute([$file->fileName, '1']);
            if ($file->content === 'fail') {
                throw new RuntimeException('after the insert');
            }
        };

        $res = Pulp::start()
            ->pipe(Pulp::src([self::rows('kept', []), self::rows('rolled-back', [])]))
            ->pipe(Pulp::map(static function (File $file): File {
                $file->content = $file->fileName === 'kept' ? 'ok' : 'fail';

                return $file;
            }))
            ->pipe(PulpPdo::transaction($this->pdo, $write, ['skipExceptions' => true, 'logSkipExceptions' => false]))
            ->run();

        $this->assertCount(2, $res);
        $this->assertSame([['kept', '1', null, null, null]], $this->stored());
    }
}

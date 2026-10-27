<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpJSON;
use OpenMapsight\PulpSearchIndex;
use OpenMapsight\pulpsearchindex\SearchIndex;
use OpenMapsight\pulpsearchindex\SearchIndexDatabase;
use PHPUnit\Framework\TestCase;

class PulpSearchIndexTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private static function source(string $fileName, string $json, array $titles = []): File
    {
        $file = new File($fileName);
        $file->content = $json;
        $file->titles = $titles;

        return $file;
    }

    private static function feed(array ...$features): string
    {
        return (string) json_encode(['type' => 'FeatureCollection', 'features' => array_map(
            static fn(array $f): array => ['type' => 'Feature', 'id' => $f[0], 'geometry' => ['type' => 'Point', 'coordinates' => $f[2] ?? [10.5, 52.25]], 'properties' => ['name' => $f[1]]],
            $features,
        )]);
    }

    /**
     * @return list<File>
     */
    private function fixtureSources(): array
    {
        $searches = json_decode((string) file_get_contents(__DIR__ . '/fixtures/searches.json'), true);
        $files = [];
        foreach ($searches['sources'] as $path => $source) {
            $files[] = self::source($path, (string) file_get_contents(__DIR__ . '/fixtures/' . $source['file']), $source['titles']);
        }

        return $files;
    }

    public function testIndexesEachSourceWithItsTitles(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src([
                self::source('/a.geojson', self::feed(['1', 'Dom'], ['2', 'Burg']), ['Kirchen']),
                self::source('/b.geojson', self::feed(['1', 'Park'])),
            ]))
            ->pipe(PulpJSON::decodeJSON())
            ->pipe(PulpSearchIndex::index('index.json'))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('index.json', $res[0]->fileName);
        $this->assertSame(1, $res[0]->content['version']);
        $this->assertSame(['/a.geojson', '/b.geojson'], array_keys($res[0]->content['sources']));
        $this->assertSame(['Kirchen'], $res[0]->content['sources']['/a.geojson']['titles']);
        $this->assertSame(['1', 'Dom', '', 'dom 1', [], [10.5, 52.25]], $res[0]->content['sources']['/a.geojson']['rows'][0]);
    }

    public function testSkipsBrokenSourcesAndEmitsNothingWithoutSources(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src([self::source('/broken.geojson', '{"type":'), self::source('/ok.geojson', self::feed(['1', 'Ok']))]))
            ->pipe(PulpSearchIndex::index(options: [
                'titles' => static fn(File $file): array => ['Titel von ' . $file->fileName],
                'skipExceptions' => true,
                'logSkipExceptions' => false,
            ]))
            ->run();

        $this->assertSame(['/ok.geojson'], array_keys($res[0]->content['sources']));
        $this->assertSame(['Titel von /ok.geojson'], $res[0]->content['sources']['/ok.geojson']['titles']);

        $this->assertSame([], Pulp::start()
            ->pipe(Pulp::src([self::source('/broken.geojson', '[]')]))
            ->pipe(PulpSearchIndex::index(options: ['skipExceptions' => true, 'logSkipExceptions' => false]))
            ->run());
    }

    public function testTheDatabaseFindsWhatTheIndexFinds(): void
    {
        $searches = json_decode((string) file_get_contents(__DIR__ . '/fixtures/searches.json'), true);
        $res = Pulp::start()
            ->pipe(Pulp::src($this->fixtureSources()))
            ->pipe(PulpSearchIndex::index())
            ->pipe(PulpSearchIndex::toDatabase(fn(): PDO => $this->pdo))
            ->run();
        $sources = json_decode(json_encode($res[0]->content['sources']), true);

        foreach (array_keys($searches['queries']) as $query) {
            $this->assertSame(
                SearchIndex::search($sources, $searches['requested'], (string) $query),
                SearchIndexDatabase::search($this->pdo, $searches['requested'], (string) $query),
                'places for "' . $query . '"',
            );
        }
        $this->assertSame(
            SearchIndex::search($sources, array_map(static fn(string $p): array => [0, $p], array_keys($sources)), 'dom', 3),
            SearchIndexDatabase::search($this->pdo, null, 'dom', 3),
            'every source without requested sources',
        );
        $this->assertSame(['places' => [], 'missing' => ['/missing.geojson']], SearchIndexDatabase::search($this->pdo, [[0, '/missing.geojson']], 'dom'));
    }

    public function testRewritesOnlyChangedSourcesAndDropsVanishedOnes(): void
    {
        $index = static fn(array $sources): array => ['sources' => array_map(
            static fn(string $json): array => ['titles' => ['T'], 'rows' => SearchIndex::rows(json_decode($json))],
            $sources,
        )];
        SearchIndexDatabase::createTables($this->pdo);
        SearchIndexDatabase::write($this->pdo, $index(['/a' => self::feed(['1', 'Alpha']), '/b' => self::feed(['1', 'Beta'])]), now: 1000);
        SearchIndexDatabase::write($this->pdo, $index(['/a' => self::feed(['1', 'Alpha']), '/c' => self::feed(['1', 'Gamma'])]), now: 2000);
        SearchIndexDatabase::write($this->pdo, $index(['/a' => self::feed(['1', 'Alpha']), '/c' => self::feed(['1', 'Gamma 2'])]), now: 3000);

        $this->assertSame(
            [['/a', 1000, 3000], ['/c', 3000, 3000]],
            $this->pdo->query('SELECT path, updated_at, indexed_at FROM search_index_sources ORDER BY path')->fetchAll(PDO::FETCH_NUM),
        );
        $this->assertSame(
            [['/a', 'Alpha'], ['/c', 'Gamma 2']],
            $this->pdo->query('SELECT path, title FROM search_index_rows ORDER BY path')->fetchAll(PDO::FETCH_NUM),
        );
        $this->assertSame(['/a' => ['T'], '/c' => ['T']], SearchIndexDatabase::titles($this->pdo));
        $this->assertSame(['/c' => ['T']], SearchIndexDatabase::titles($this->pdo, ['/c', '/x']));
    }

    public function testAFailedWriteKeepsTheStoredIndex(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src([self::source('/a', self::feed(['1', 'Alpha']))]))
            ->pipe(PulpSearchIndex::index())
            ->pipe(PulpSearchIndex::toDatabase($this->pdo, ['prefix' => 'places']))
            ->run();
        $broken = clone $res[0];
        $broken->content = ['sources' => ['/a' => ['titles' => [], 'rows' => [['1', 'Alpha', '', 'alpha', [], [NAN, 1]]]]]];

        Pulp::start()
            ->pipe(Pulp::src($broken))
            ->pipe(PulpSearchIndex::toDatabase($this->pdo, ['prefix' => 'places', 'skipExceptions' => true, 'logSkipExceptions' => false]))
            ->run();

        $this->assertSame([['1', '[10.5,52.25]']], $this->pdo->query('SELECT id, point FROM places_rows')->fetchAll(PDO::FETCH_NUM));
    }
}

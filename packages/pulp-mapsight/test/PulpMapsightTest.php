<?php

declare(strict_types=1);

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpMapsight;
use PHPUnit\Framework\TestCase;

class PulpMapsightTest extends TestCase
{
    private static function page(string $fileName, string $html): File
    {
        $file = new File($fileName);
        $file->content = $html;

        return $file;
    }

    public function testEmitsOneFilePerSourceOnTheOrigin(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src(self::page('plan.html', (string) file_get_contents(__DIR__ . '/fixtures/page.html'))))
            ->pipe(PulpMapsight::featureSources('http://www.example.org/'))
            ->run();

        $this->assertSame(['/geojson/museen.geojson', '/leben/parks.php?sp:out=geoJSON&v=2'], array_map(static fn(File $f): string => $f->fileName, $res));
        $this->assertSame([
            'url' => 'http://www.example.org/geojson/museen.geojson',
            'path' => '/geojson/museen.geojson',
            'featureSourceIds' => ['museen', 'museumsAgain'],
            'titles' => ['Museen', 'Kultur', 'Theater'],
        ], $res[0]->content);
        $this->assertSame([], $res[1]->content['titles'], 'empty titles are left out');
    }

    public function testMergesPagesAndReadsOtherAttributesAndTypes(): void
    {
        $state = htmlspecialchars((string) json_encode([
            'featureSources' => ['stops' => ['type' => 'geojson-url', 'url' => '/stops.geojson']],
            'map' => ['layers' => [['metaData' => ['title' => 'Haltestellen'], 'options' => ['source' => ['options' => ['featureSourceId' => 'stops']]]]]],
        ]), ENT_QUOTES);

        $res = Pulp::start()
            ->pipe(Pulp::src([
                self::page('a.html', '<div data-state="' . $state . '"></div>'),
                self::page('b.html', '<div data-state="' . $state . '"></div><p>no map</p>'),
            ]))
            ->pipe(PulpMapsight::featureSources('https://www.example.org', ['attribute' => 'data-state', 'types' => ['geojson-']]))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame(['stops'], $res[0]->content['featureSourceIds']);
        $this->assertSame(['Haltestellen'], $res[0]->content['titles']);
    }

    public function testPagesWithoutSourcesEmitNothing(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src(self::page('empty.html', '<div data-dehydrated-state="not json"></div>')))
            ->pipe(PulpMapsight::featureSources('https://www.example.org'))
            ->run();

        $this->assertSame([], $res);
    }

    public function testTheOriginNeedsAHost(): void
    {
        $this->expectException(RuntimeException::class);

        Pulp::start()
            ->pipe(Pulp::src(self::page('a.html', '')))
            ->pipe(PulpMapsight::featureSources('/relative'))
            ->run();
    }
}

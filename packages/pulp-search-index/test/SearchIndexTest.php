<?php

declare(strict_types=1);

use OpenMapsight\pulpsearchindex\SearchIndex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The fixtures pin rows and ranking; clients that index or rank themselves
 * check the same files.
 */
class SearchIndexTest extends TestCase
{
    private static function fixture(string $name, bool $associative): mixed
    {
        return json_decode((string) file_get_contents(__DIR__ . '/fixtures/' . $name), $associative, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function decodings(): array
    {
        return ['objects' => [false], 'arrays' => [true]];
    }

    #[DataProvider('decodings')]
    public function testRowsMatchTheFixture(bool $associative): void
    {
        $rows = json_decode(json_encode(SearchIndex::rows(self::fixture('features.geojson', $associative)), JSON_THROW_ON_ERROR), true);

        $this->assertSame(self::fixture('rows.json', true), $rows);
    }

    public function testRankingMatchesTheFixture(): void
    {
        $searches = self::fixture('searches.json', true);
        $sources = [];
        foreach ($searches['sources'] as $path => $source) {
            $rows = SearchIndex::rows(self::fixture($source['file'], false));
            $sources[$path] = ['titles' => $source['titles'], 'rows' => json_decode(json_encode($rows), true)];
        }

        foreach ($searches['queries'] as $query => $expected) {
            $result = SearchIndex::search($sources, $searches['requested'], (string) $query);
            $this->assertSame($expected, array_map(static fn(array $row): array => [$row[0], $row[1], $row[5]], $result['places']), 'ranking for "' . $query . '"');
            $this->assertSame(['/missing.geojson'], $result['missing']);
        }
    }

    public function testTextIsPlainAndNormalized(): void
    {
        $this->assertSame('strasse gross', SearchIndex::normalize('Straße GROẞ'));
        $this->assertSame('<img src=x onerror=alert(1)>', SearchIndex::plainText('&lt;img src=x onerror=alert(1)&gt;'));
        $this->assertSame('© Foto „BS“', SearchIndex::plainText('&copy; Foto <b>&bdquo;BS&ldquo;</b>'));
        $this->assertSame(['hauptfriedhof', '7'], SearchIndex::tokens(' Hauptfriedhof, 7 '));
        $this->assertSame('7', SearchIndex::numberKey('007'));
    }

    public function testRowsNeedAFeatureCollection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SearchIndex::rows(['type' => 'Feature']);
    }
}

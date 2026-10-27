<?php

declare(strict_types=1);

namespace OpenMapsight\pulpmapsight;

/**
 * Feature sources in the dehydrated state of Mapsight embeds.
 */
final class FeatureSources
{
    /**
     * Sources of the given types on `$host` (or relative), by path with
     * query, with the ids they have in the state and the titles of the map
     * layers showing them.
     *
     * @param list<string> $types Type prefixes, e.g. `xhr-json`
     * @return array<string, array{featureSourceIds: list<string>, titles: list<string>}>
     */
    public static function fromHtml(string $html, string $host, string $attribute = 'data-dehydrated-state', array $types = ['xhr-json']): array
    {
        $pattern = '/\s' . preg_quote($attribute, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
        $found = [];
        foreach ($matches as $match) {
            $raw = ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');
            $state = json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (!is_array($state)) {
                continue;
            }
            $paths = [];
            foreach (is_array($state['featureSources'] ?? null) ? $state['featureSources'] : [] as $id => $source) {
                $type = is_array($source) ? ($source['type'] ?? null) : null;
                $url = is_array($source) ? ($source['url'] ?? null) : null;
                if (!is_string($type) || !is_string($url) || !self::hasType($type, $types)) {
                    continue;
                }
                $path = self::path($url, $host);
                if ($path === null) {
                    continue;
                }
                $paths[(string) $id] = $path;
                $found[$path] ??= ['featureSourceIds' => [], 'titles' => []];
                $found[$path]['featureSourceIds'][] = (string) $id;
            }
            foreach (is_array($state['map']['layers'] ?? null) ? $state['map']['layers'] : [] as $layer) {
                $id = is_array($layer) ? ($layer['options']['source']['options']['featureSourceId'] ?? null) : null;
                $title = is_array($layer) ? ($layer['metaData']['title'] ?? null) : null;
                if (is_string($id) && isset($paths[$id]) && is_string($title) && trim($title) !== '') {
                    $found[$paths[$id]]['titles'][] = trim($title);
                }
            }
        }

        return array_map(static fn(array $source): array => [
            'featureSourceIds' => array_values(array_unique($source['featureSourceIds'])),
            'titles' => array_values(array_unique($source['titles'])),
        ], $found);
    }

    /**
     * Path with query of a URL on `$host`, or of a relative URL; null for
     * other hosts and schemes.
     */
    public static function path(string $url, string $host): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || !str_starts_with((string) ($parts['path'] ?? ''), '/')) {
            return null;
        }
        if (isset($parts['host']) && strcasecmp($parts['host'], $host) !== 0) {
            return null;
        }
        if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    /**
     * @param list<string> $types
     */
    private static function hasType(string $type, array $types): bool
    {
        foreach ($types as $prefix) {
            if (str_starts_with($type, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

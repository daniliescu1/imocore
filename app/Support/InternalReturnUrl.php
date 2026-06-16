<?php

namespace App\Support;

class InternalReturnUrl
{
    public static function normalize(mixed $url): ?string
    {
        $url = trim((string) $url);

        if (preg_match('#^https?://#i', $url)) {
            $parsed = parse_url($url);
            $path = $parsed['path'] ?? '';
            $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';
            $url = $path.$query;
        }

        if ($url === '' || ! str_starts_with($url, '/')) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        if (! preg_match('#^/(spatii|contracte|configurare-anexa)/#', $path)) {
            return null;
        }

        return $url;
    }
}

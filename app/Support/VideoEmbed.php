<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns the link an admin pasted into one a browser will actually play.
 *
 * Every provider serves two URLs for the same video: the page a human visits,
 * and the frame another site may embed. The page form answers with
 * `X-Frame-Options: SAMEORIGIN`, so dropping it straight into an <iframe>
 * renders an empty black box and reports nothing anywhere. Admins paste what
 * their address bar shows, which is always the page form, so this mapping is
 * the difference between a lesson that plays and one that silently does not.
 *
 * Anything we have no mapping for is returned untouched: a host we do not
 * recognise may well be a working embed already, and guessing would break it.
 */
final class VideoEmbed
{
    /** Extensions a <video> element can take as a direct source. */
    private const MEDIA_EXTENSIONS = ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v'];

    /** YouTube video ids are 11 characters of the URL-safe base64 alphabet. */
    private const YOUTUBE_ID = '/^[A-Za-z0-9_-]{11}$/';

    public static function isRemote(?string $path): bool
    {
        return Str::startsWith((string) $path, ['http://', 'https://']);
    }

    /**
     * A remote URL pointing at a video file rather than a player page. These
     * belong in <video>, where the poster, the download guard and progress
     * tracking all keep working, not in an iframe.
     */
    public static function isDirectMedia(?string $url): bool
    {
        if (! self::isRemote($url)) {
            return false;
        }

        $path = (string) parse_url((string) $url, PHP_URL_PATH);

        return in_array(
            Str::lower(pathinfo($path, PATHINFO_EXTENSION)),
            self::MEDIA_EXTENSIONS,
            true,
        );
    }

    /**
     * The embeddable form of a provider URL, or the URL unchanged when its host
     * is not one we know how to rewrite.
     */
    public static function resolve(?string $url): string
    {
        $url = trim((string) $url);

        if (! self::isRemote($url)) {
            return $url;
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return $url;
        }

        $host = (string) preg_replace('/^www\./', '', Str::lower($parts['host']));
        $path = trim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);

        return match (true) {
            self::isYoutube($host) => self::youtube($host, $path, $query, $url),
            in_array($host, ['youtube-nocookie.com', 'player.vimeo.com'], true) => $url,
            $host === 'vimeo.com' => self::vimeo($path, $query, $url),
            $host === 'drive.google.com' => self::googleDrive($path, $url),
            default => $url,
        };
    }

    /**
     * A link on a domain RFC 2606 reserves for documentation. These can never
     * resolve for anyone, so a lesson pointing at one is seed data nobody has
     * replaced yet -- worth saying out loud rather than leaving a learner with
     * a black rectangle.
     */
    public static function isPlaceholder(?string $url): bool
    {
        $host = Str::lower((string) parse_url((string) $url, PHP_URL_HOST));

        return $host !== ''
            && (Str::endsWith($host, ['.example', '.invalid', '.test', '.localhost'])
                || (bool) preg_match('/(^|\.)example\.(com|net|org)$/', $host));
    }

    /**
     * True when resolve() had to rewrite the link, i.e. the stored URL on its
     * own would not have played. The admin form says so, rather than leaving
     * them to find out from a learner.
     */
    public static function wasRewritten(?string $url): bool
    {
        return self::resolve($url) !== trim((string) $url);
    }

    private static function isYoutube(string $host): bool
    {
        return in_array($host, ['youtube.com', 'youtu.be', 'm.youtube.com', 'music.youtube.com'], true);
    }

    /**
     * watch?v=ID, youtu.be/ID, /shorts/ID and /live/ID all describe the same
     * video; only /embed/ID may be framed. A start offset arrives as either
     * `t` (1m30s or 90s) or `start` (seconds), but the player reads `start`.
     */
    private static function youtube(string $host, string $path, array $query, string $url): string
    {
        $id = match (true) {
            $host === 'youtu.be' => Str::before($path, '/'),
            Str::startsWith($path, 'watch') => $query['v'] ?? '',
            (bool) preg_match('~^(?:embed|shorts|live|v)/([^/?#]+)~', $path, $m) => $m[1],
            default => '',
        };

        if (! preg_match(self::YOUTUBE_ID, (string) $id)) {
            return $url;
        }

        $params = array_filter([
            'start' => self::seconds($query['start'] ?? $query['t'] ?? null) ?: null,
            'list' => $query['list'] ?? null,
        ]);

        return "https://www.youtube.com/embed/{$id}".($params ? '?'.http_build_query($params) : '');
    }

    /**
     * vimeo.com/ID is the page; player.vimeo.com/video/ID is the frame. A
     * second path segment is the unlisted-video hash, which the player needs
     * as ?h= or it answers 404.
     */
    private static function vimeo(string $path, array $query, string $url): string
    {
        if (! preg_match('~^(\d+)(?:/([A-Za-z0-9]+))?~', $path, $m)) {
            return $url;
        }

        $hash = $m[2] ?? ($query['h'] ?? null);

        return "https://player.vimeo.com/video/{$m[1]}".($hash ? '?'.http_build_query(['h' => $hash]) : '');
    }

    /** A Drive share link ends /view; the embeddable one ends /preview. */
    private static function googleDrive(string $path, string $url): string
    {
        return preg_match('~^file/d/([^/?#]+)~', $path, $m)
            ? "https://drive.google.com/file/d/{$m[1]}/preview"
            : $url;
    }

    /** Accepts 90, 90s, 1m30s or 1h2m3s and returns whole seconds. */
    private static function seconds(?string $value): int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        if (! preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $value, $m)) {
            return 0;
        }

        return ((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60 + ((int) ($m[3] ?? 0));
    }
}

<?php

namespace App\Support;

use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Short-lived, viewer-bound addresses for an uploaded lesson.
 *
 * Two independent things have to hold before a byte is served:
 *
 *  1. A valid HMAC signature that has not expired. Laravel's own signed-URL
 *     machinery does this, keyed on APP_KEY.
 *  2. A binding token proving the link was minted for THIS viewer in THIS
 *     session. A link copied out of devtools and pasted into another browser
 *     fails here even while the signature is still inside its window.
 *
 * (2) is the part that actually stops sharing. (1) narrows how long a copied
 * link is worth anything to the person who copied it.
 */
final class StreamToken
{
    /** Where the per-session secret lives. */
    private const SESSION_KEY = 'tpa.stream_key';

    /**
     * A random value minted once per session and never sent to the client.
     *
     * Deliberately NOT the session id: Laravel rotates that on login to defeat
     * session fixation, which would silently invalidate every link a visitor
     * was holding at the moment they signed in.
     */
    /** The session attribute holding that secret. */
    public static function sessionKeyName(): string
    {
        return self::SESSION_KEY;
    }

    public static function sessionKey(): string
    {
        if (! session()->has(self::SESSION_KEY)) {
            session()->put(self::SESSION_KEY, Str::random(40));
        }

        return (string) session()->get(self::SESSION_KEY);
    }

    /**
     * The binding half: who the link was minted for.
     *
     * An HMAC over the viewer's identity keyed with APP_KEY, so the client can
     * neither read it nor forge it. A guest is bound to their session key
     * alone, which is what lets a free lesson play without an account while
     * still refusing a link that has been handed to somebody else.
     */
    public static function for(Video $video, ?User $user, string $key): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [$video->getKey(), $user?->getKey() ?? 'guest', $key]),
            (string) config('app.key'),
        );
    }

    /** A signed, expiring, viewer-bound URL for this lesson. */
    public static function url(Video $video, ?User $user = null, ?string $key = null): string
    {
        return URL::temporarySignedRoute(
            'videos.stream',
            now()->addSeconds(self::ttl()),
            ['video' => $video, 'token' => self::for($video, $user, $key ?? self::sessionKey())],
        );
    }

    /**
     * How long a freshly minted link may be used to START a stream.
     *
     * Short on purpose. It is not how long playback lasts -- see
     * VideoStreamController::grant(), which is what carries an authorised
     * viewer through a lesson longer than this window.
     */
    public static function ttl(): int
    {
        return (int) config('academy.streaming.link_ttl', 60);
    }

    /**
     * Constant-time comparison, so a wrong token cannot be narrowed down by
     * timing how long the rejection took.
     */
    public static function matches(string $candidate, Video $video, ?User $user, ?string $key = null): bool
    {
        return hash_equals(self::for($video, $user, $key ?? self::sessionKey()), $candidate);
    }
}

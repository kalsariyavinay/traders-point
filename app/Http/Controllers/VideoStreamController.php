<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Support\StreamToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoStreamController extends Controller
{
    /**
     * Serve an uploaded lesson from the private disk, in capped slices, to a
     * viewer who is entitled to it and holds a link minted for them.
     *
     * The checks run cheapest-first and every one of them answers 403/404
     * rather than describing what went wrong: a probe should not be able to
     * tell "wrong token" from "expired" from "not purchased".
     */
    public function __invoke(Request $request, Video $video, string $token): StreamedResponse
    {
        $user = $request->user();

        abort_unless($video->isPublished() || $user?->isAdmin(), 404);
        abort_unless($video->isAccessibleBy($user), 403);

        // External videos are embedded by the player, never proxied by us.
        abort_if($video->isExternalVideo(), 404);

        // Who was this link minted for? A link lifted from one browser and
        // replayed in another dies here, signature still valid or not.
        abort_unless(StreamToken::matches($token, $video, $user), 403);

        /*
         * A forged signature is fatal, always. Checked separately from expiry
         * on purpose: an earlier version asked hasValidSignature() -- which is
         * correct-AND-unexpired -- and fell through to the grant whenever it
         * said no. That meant a session which had already started the lesson
         * legitimately would then accept a TAMPERED signature, because the
         * grant alone carried it. Unit tests missed it (no prior grant); a
         * live run against Apache caught it.
         */
        abort_unless(URL::hasCorrectSignature($request), 403);

        /*
         * Signature genuine. If it is also still inside its window this is a
         * fresh link, so start the clock; otherwise it is a link this session
         * already used, and only an unexpired grant may carry it on. Without
         * that second branch a 60-second link would cut playback off after a
         * minute, because a <video> keeps asking for more bytes on every seek
         * and every buffer refill.
         */
        abort_unless(
            URL::signatureHasNotExpired($request)
                ? $this->grant($request, $video)
                : $this->granted($request, $video),
            403,
        );

        $path = Storage::disk('local')->path($video->video_path);

        abort_unless(is_file($path), 404);

        return $this->slice($request, $path, (string) Storage::disk('local')->mimeType($video->video_path));
    }

    /**
     * Record that this session may keep reading this lesson, and for how long.
     *
     * Kept in the session rather than a cookie of its own: the session is
     * already the thing the token is bound to, so this adds no new secret for
     * an attacker to steal, and it disappears when they sign out.
     */
    private function grant(Request $request, Video $video): bool
    {
        $window = min(
            ($video->duration_seconds ?? 0) + self::GRANT_MARGIN_SECONDS,
            (int) config('academy.streaming.max_grant', 21600),
        );

        $request->session()->put(
            "stream_grants.{$video->getKey()}",
            now()->addSeconds(max($window, self::GRANT_MARGIN_SECONDS))->getTimestamp(),
        );

        return true;
    }

    private function granted(Request $request, Video $video): bool
    {
        $expiresAt = $request->session()->get("stream_grants.{$video->getKey()}");

        return is_int($expiresAt) && $expiresAt >= now()->getTimestamp();
    }

    /** Pauses and re-watches are normal; the grant outlives the lesson itself. */
    private const GRANT_MARGIN_SECONDS = 1800;

    /**
     * Answer with at most one capped slice.
     *
     * A plain file response hands the whole .mp4 over on one connection, which
     * is exactly what a download manager wants. Clamping every response to a
     * few megabytes means pulling the file whole takes hundreds of requests,
     * each of which has to pass every check above and stops the moment the
     * grant lapses or the viewer signs out.
     */
    private function slice(Request $request, string $path, string $mime): StreamedResponse
    {
        $size = (int) filesize($path);
        $chunk = (int) config('academy.streaming.chunk_bytes', 2097152);

        $range = $this->parseRange($request->header('Range'), $size);

        if ($range === false) {
            return $this->unsatisfiable($size);
        }

        [$start, $end] = $range;

        // The clamp is the point: ask for the whole file and you get a slice.
        $end = min($end, $start + $chunk - 1, $size - 1);
        $length = $end - $start + 1;

        return response()->stream(
            function () use ($path, $start, $length) {
                $handle = fopen($path, 'rb');
                fseek($handle, $start);

                $remaining = $length;

                while ($remaining > 0 && ! feof($handle)) {
                    $buffer = fread($handle, (int) min(8192, $remaining));

                    if ($buffer === false || $buffer === '') {
                        break;
                    }

                    echo $buffer;
                    flush();

                    $remaining -= strlen($buffer);
                }

                fclose($handle);
            },
            206,
            [
                'Content-Type' => $mime,
                'Content-Length' => (string) $length,
                'Content-Range' => "bytes {$start}-{$end}/{$size}",
                'Accept-Ranges' => 'bytes',
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
                // Never let a shared proxy or the browser keep a copy that
                // outlives the grant that authorised it.
                'Cache-Control' => 'no-store, no-cache, private, max-age=0',
            ],
        );
    }

    /**
     * @return array{0: int, 1: int}|false  false when the header is present but unsatisfiable
     */
    private function parseRange(?string $header, int $size): array|false
    {
        // No Range header at all: hand back the opening slice rather than the
        // file. Browsers always send one for media; the clients that do not
        // are the ones we are least interested in serving whole.
        if (! $header || ! preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $m)) {
            return [0, $size - 1];
        }

        [, $from, $to] = $m;

        if ($from === '' && $to === '') {
            return false;
        }

        // "bytes=-500" means the last 500 bytes.
        if ($from === '') {
            $start = max(0, $size - (int) $to);
            $end = $size - 1;
        } else {
            $start = (int) $from;
            $end = $to === '' ? $size - 1 : (int) $to;
        }

        if ($start > $end || $start >= $size) {
            return false;
        }

        return [$start, min($end, $size - 1)];
    }

    private function unsatisfiable(int $size): StreamedResponse
    {
        return response()->stream(fn () => null, 416, [
            'Content-Range' => "bytes */{$size}",
            'Accept-Ranges' => 'bytes',
        ]);
    }
}

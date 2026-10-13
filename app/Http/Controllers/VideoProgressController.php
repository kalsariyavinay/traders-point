<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VideoProgressController extends Controller
{
    /** A day of video is far beyond anything real; anything above is junk. */
    private const MAX_SECONDS = 86400;

    /**
     * Beacon endpoint: the player posts where the learner currently is.
     *
     * Deliberately tiny -- it answers 204 with no body, because it is called
     * every few seconds and on page unload via navigator.sendBeacon.
     */
    public function __invoke(Request $request, Video $video): Response
    {
        $user = $request->user();

        abort_unless($video->isPublished() || $user->isAdmin(), 404);
        abort_unless($video->isAccessibleBy($user), 403);

        $validated = $request->validate([
            'progress_seconds' => ['required', 'integer', 'min:0', 'max:'.self::MAX_SECONDS],
        ]);

        $user->recordProgress($video, (int) $validated['progress_seconds']);

        return response()->noContent();
    }
}

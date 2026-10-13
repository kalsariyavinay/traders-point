<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicVideoController extends Controller
{
    public function show(Request $request, Video $video): View
    {
        $user = $request->user();

        // Drafts stay invisible to the public; admins may preview them.
        abort_unless($video->isPublished() || $user?->isAdmin(), 404);

        $video->load('category');

        $hasAccess = $video->isAccessibleBy($user);

        // Opening a lesson you can watch puts it in your dashboard.
        if ($user && $hasAccess && $video->isPublished()) {
            $user->recordWatch($video);
        }

        $related = Video::query()
            // Each card links its category, which is one query per card without this.
            ->with('category')
            ->published()
            ->where('category_id', $video->category_id)
            ->whereKeyNot($video->getKey())
            ->latest()
            ->take(3)
            ->get();

        return view('videos.show', [
            'video' => $video,
            'hasAccess' => $hasAccess,
            // Where to drop the playhead when the lesson reopens.
            'resumeAt' => $user && $hasAccess ? $user->resumePositionFor($video) : 0,
            'related' => $related,
            // Resolved once so the cards can show owned/locked without a query each,
            // the same way the landing page and the dashboard already do it.
            'ownedIds' => $user?->purchasedVideos()
                ->whereIn('videos.id', $related->modelKeys())
                ->pluck('videos.id')
                ->all() ?? [],
        ]);
    }
}

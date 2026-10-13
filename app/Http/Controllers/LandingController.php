<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $categorySlug = (string) $request->query('category', '');
        $freeOnly = $request->boolean('free');

        $videos = Video::query()
            ->with('category')
            ->published()
            ->when($categorySlug !== '', fn (Builder $query) => $query->whereHas(
                'category',
                fn (Builder $q) => $q->where('slug', $categorySlug)
            ))
            ->when($freeOnly, fn (Builder $query) => $query->free())
            ->orderByDesc('is_free')
            ->latest()
            ->paginate(9)
            ->withQueryString()
            ->fragment('courses');

        return view('welcome', [
            // Only offer a tab for a category that actually has something to watch.
            'categories' => Category::whereHas('videos', fn (Builder $q) => $q->published())
                ->withCount(['videos' => fn (Builder $q) => $q->published()])
                ->orderBy('name')
                ->get(),
            'videos' => $videos,
            'activeCategory' => $categorySlug,
            'freeOnly' => $freeOnly,
            'freeCount' => Video::published()->free()->count(),
            // Resolved once so the cards can show owned/locked without a query each.
            'ownedIds' => $request->user()?->purchasedVideos()->pluck('videos.id')->all() ?? [],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\VideoStatus;
use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const FILTERS = ['all', 'purchased', 'free'];

    /**
     * The learner's panel: every lesson they have bought, plus every free one
     * they have opened, newest activity first.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $filter = (string) $request->query('filter', 'all');

        if (! in_array($filter, self::FILTERS, true)) {
            $filter = 'all';
        }

        $videos = Video::query()
            ->select('videos.*')
            ->with('category')
            ->published()
            ->addSelect([
                'last_watched_at' => $this->pivotColumn('video_views', 'updated_at', $user),
                'purchased_at' => $this->pivotColumn('video_user', 'updated_at', $user),
                'progress_seconds' => $this->pivotColumn('video_views', 'progress_seconds', $user),
                'completed_at' => $this->pivotColumn('video_views', 'completed_at', $user),
            ])
            ->tap(fn (Builder $query) => match ($filter) {
                'purchased' => $query->whereHas('buyers', $this->isThisUser()),
                'free' => $query->where('is_free', true)->whereHas('viewers', $this->isThisUser()),
                default => $query->where(fn (Builder $q) => $q
                    ->whereHas('buyers', $this->isThisUser())
                    ->orWhereHas('viewers', $this->isThisUser())),
            })
            // A purchase with no watch yet still sorts by when it was bought.
            ->orderByRaw('COALESCE(last_watched_at, purchased_at) DESC')
            ->paginate(9)
            ->withQueryString();

        return view('dashboard', [
            'videos' => $videos,
            'filter' => $filter,
            'purchasedCount' => $user->purchasedVideos()->count(),
            'watchedCount' => $user->watchedVideos()->where('is_free', true)->count(),
            'completedCount' => $user->watchedVideos()->wherePivotNotNull('completed_at')->count(),
            'spent' => (float) $user->orders()->where('status', OrderStatus::Success)->sum('amount'),
            // Resolved once so the cards can show owned/locked without a query each.
            'ownedIds' => $user->purchasedVideos()->pluck('videos.id')->all(),
            'certificates' => $this->certificates($user),
        ]);
    }

    /**
     * One correlated subquery per pivot column, so a page of cards costs no
     * extra round trips.
     */
    private function pivotColumn(string $table, string $column, User $user): QueryBuilder
    {
        return DB::table($table)
            ->select($column)
            ->whereColumn('video_id', 'videos.id')
            ->where('user_id', $user->id)
            ->limit(1);
    }

    /**
     * Certificate progress for every category the learner has started.
     *
     * One grouped aggregate rather than a query per category.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function certificates(User $user): Collection
    {
        $progress = DB::table('video_views')
            ->join('videos', 'videos.id', '=', 'video_views.video_id')
            ->where('video_views.user_id', $user->id)
            ->where('videos.status', VideoStatus::Published->value)
            ->groupBy('videos.category_id')
            ->selectRaw('videos.category_id as category_id')
            ->selectRaw('SUM(CASE WHEN video_views.completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed')
            ->selectRaw('MAX(video_views.completed_at) as completed_at')
            ->get()
            ->keyBy('category_id');

        if ($progress->isEmpty()) {
            return collect();
        }

        return Category::query()
            ->whereIn('id', $progress->keys())
            ->withCount(['videos as total_lessons' => fn (Builder $q) => $q->published()])
            ->orderBy('name')
            ->get()
            ->map(function (Category $category) use ($progress) {
                $row = $progress[$category->id];
                $completed = (int) $row->completed;
                $total = (int) $category->total_lessons;

                return [
                    'category' => $category,
                    'completed' => $completed,
                    'total' => $total,
                    'earned' => $total > 0 && $completed === $total,
                    'completed_at' => $row->completed_at ? Carbon::parse($row->completed_at) : null,
                ];
            })
            ->values();
    }

    private function isThisUser(): callable
    {
        $id = request()->user()->id;

        return fn (Builder|QueryBuilder $query) => $query->where('users.id', $id);
    }
}

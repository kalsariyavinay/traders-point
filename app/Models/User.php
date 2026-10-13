<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\VideoStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Paid videos this user has been granted access to.
     *
     * @return BelongsToMany<Video, $this>
     */
    public function purchasedVideos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'video_user')->withTimestamps();
    }

    /**
     * Videos this user has opened and could watch, free or paid.
     *
     * @return BelongsToMany<Video, $this>
     */
    public function watchedVideos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'video_views')
            ->withPivot(['progress_seconds', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * Remember that the user watched this video, or bump the time if they are
     * back for another pass.
     */
    public function recordWatch(Video $video): void
    {
        /*
         * An upsert touches exactly this one row. syncWithoutDetaching() first
         * reads EVERY pivot row this learner has, to work out what to change --
         * an unbounded SELECT on every lesson open, and again on every progress
         * beacon. video_views carries unique(user_id, video_id), which is what
         * makes the upsert resolve to a single row.
         */
        $now = now();

        DB::table('video_views')->upsert(
            [[
                'user_id' => $this->getKey(),
                'video_id' => $video->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['user_id', 'video_id'],
            // progress_seconds defaults to 0 and completed_at is nullable, so
            // neither is listed: reopening a lesson must not reset either.
            ['updated_at'],
        );

        $this->unsetRelation('watchedVideos');
    }

    /**
     * Store how far into a video the learner got, and decide here -- never in
     * the browser -- whether that counts as finishing it.
     *
     * The position is clamped to the known duration, so a forged beacon cannot
     * claim more playback than the lesson contains.
     */
    public function recordProgress(Video $video, int $seconds): void
    {
        $seconds = max(0, $seconds);

        if ($video->duration_seconds) {
            $seconds = min($seconds, $video->duration_seconds);
        }

        $now = now();

        $row = [
            'user_id' => $this->getKey(),
            'video_id' => $video->getKey(),
            'progress_seconds' => $seconds,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $update = ['progress_seconds', 'updated_at'];

        $threshold = $video->completionThreshold();

        // Completion is sticky: rewatching resets the playhead, not the badge.
        // completed_at is only ever in the update list on the run that earns
        // it, so a later rewatch cannot overwrite the original date.
        if ($threshold !== null && $seconds >= $threshold && ! $this->hasCompleted($video)) {
            $row['completed_at'] = $now;
            $update[] = 'completed_at';
        }

        DB::table('video_views')->upsert([$row], ['user_id', 'video_id'], $update);

        $this->unsetRelation('watchedVideos');
    }

    public function progressFor(Video $video): int
    {
        return (int) ($this->watchStateFor($video)?->pivot->progress_seconds ?? 0);
    }

    public function hasCompleted(Video $video): bool
    {
        return $this->watchStateFor($video)?->pivot->completed_at !== null;
    }

    /** Where to drop the playhead. A finished lesson starts from the top. */
    public function resumePositionFor(Video $video): int
    {
        $state = $this->watchStateFor($video);

        if (! $state || $state->pivot->completed_at !== null) {
            return 0;
        }

        return (int) $state->pivot->progress_seconds;
    }

    private function watchStateFor(Video $video): ?Video
    {
        return $this->watchedVideos()->whereKey($video->getKey())->first();
    }

    /**
     * A category is finished when every published lesson in it is complete --
     * paid ones included, so they have to be bought first.
     */
    public function hasCompletedCategory(Category $category): bool
    {
        $published = $category->videos()->published()->count();

        return $published > 0 && $this->completedCountIn($category) === $published;
    }

    public function completedCountIn(Category $category): int
    {
        return $this->completedIn($category)->count();
    }

    /** When the last lesson of the category was finished -- the certificate date. */
    public function categoryCompletedAt(Category $category): ?Carbon
    {
        $latest = $this->completedIn($category)->max('video_views.completed_at');

        return $latest ? Carbon::parse($latest) : null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Video, $this>
     */
    private function completedIn(Category $category): BelongsToMany
    {
        return $this->watchedVideos()
            ->where('videos.category_id', $category->getKey())
            ->where('videos.status', VideoStatus::Published)
            ->wherePivotNotNull('completed_at');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function hasPurchased(Video $video): bool
    {
        return $this->purchasedVideos()
            ->whereKey($video->getKey())
            ->exists();
    }
}

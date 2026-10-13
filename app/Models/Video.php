<?php

namespace App\Models;

use App\Enums\VideoStatus;
use App\Models\Concerns\HasUniqueSlug;
use App\Support\StreamToken;
use App\Support\VideoEmbed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Video extends Model
{
    use HasFactory, HasUniqueSlug;

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'description',
        'thumbnail',
        'video_path',
        'duration_seconds',
        'price',
        'is_free',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_free' => 'boolean',
            'duration_seconds' => 'integer',
            'status' => VideoStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Users who have been granted access to this video.
     *
     * @return BelongsToMany<User, $this>
     */
    public function buyers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'video_user')->withTimestamps();
    }

    /**
     * Users who have opened this video.
     *
     * @return BelongsToMany<User, $this>
     */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'video_views')
            ->withPivot('progress_seconds')
            ->withTimestamps();
    }

    /**
     * @param  Builder<Video>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', VideoStatus::Published);
    }

    /**
     * @param  Builder<Video>  $query
     */
    public function scopeFree(Builder $query): void
    {
        $query->where('is_free', true);
    }

    /**
     * @param  Builder<Video>  $query
     */
    public function scopePaid(Builder $query): void
    {
        $query->where('is_free', false);
    }

    /**
     * Seconds of playback that count as having finished the lesson. Null when
     * the admin has not entered a duration, in which case nothing can complete.
     */
    public function completionThreshold(): ?int
    {
        return $this->duration_seconds
            ? (int) ceil($this->duration_seconds * 0.9)
            : null;
    }

    public function isPublished(): bool
    {
        return $this->status === VideoStatus::Published;
    }

    /**
     * Free videos are open to everyone; paid ones need a recorded purchase.
     */
    public function isAccessibleBy(?User $user): bool
    {
        if ($this->is_free) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $user->isAdmin() || $user->hasPurchased($this);
    }

    /**
     * True when video_path holds a remote URL rather than a file on our disk.
     * Decides how the video is stored and deleted, not how it is played.
     */
    public function isExternalVideo(): bool
    {
        return VideoEmbed::isRemote($this->video_path);
    }

    /**
     * Needs a provider's <iframe>. A remote URL that points straight at a
     * video file does not: it plays in our own <video> element, which keeps
     * the poster, the download guard and progress tracking.
     */
    public function isEmbeddedVideo(): bool
    {
        return $this->isExternalVideo() && ! VideoEmbed::isDirectMedia($this->video_path);
    }

    /**
     * The URL for the iframe. Normalised, because the link an admin pastes is
     * the page form, which providers refuse to be framed in.
     */
    public function embedUrl(): string
    {
        return VideoEmbed::resolve($this->video_path);
    }

    /** Points at a reserved domain, so it cannot play for anyone. */
    public function hasPlaceholderLink(): bool
    {
        return VideoEmbed::isPlaceholder($this->video_path);
    }

    /**
     * The source for a <video> element: the remote file, or a signed link to
     * our own stream that is bound to whoever is asking right now and expires
     * in seconds. Reads the current request on purpose -- the address is only
     * meaningful for the viewer the page is being rendered for.
     */
    public function playbackUrl(): string
    {
        return $this->isExternalVideo()
            ? (string) $this->video_path
            : StreamToken::url($this, auth()->user());
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail
            ? Storage::disk('public')->url($this->thumbnail)
            : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

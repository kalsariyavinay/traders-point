<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A certificate, once issued, is a record rather than a calculation.
 *
 * It used to be derived on every request, which meant adding a lesson to a
 * category silently invalidated certificates people had already printed or
 * linked to. Issuing it once fixes the reference for good and gives
 * /verify/{reference} something to answer from.
 */
class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'reference',
        'lessons_count',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'lessons_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Issue once, then always return the same certificate. The caller is
     * responsible for checking that the learner has earned it.
     */
    public static function issueFor(User $user, Category $category): self
    {
        $completedAt = $user->categoryCompletedAt($category) ?? now();

        return static::firstOrCreate(
            ['user_id' => $user->id, 'category_id' => $category->id],
            [
                'reference' => static::buildReference($user, $category, $completedAt),
                'lessons_count' => $category->videos()->published()->count(),
                'completed_at' => $completedAt,
            ],
        );
    }

    /**
     * TPA-{category}-{user}-{8 hex}. The hex is an HMAC against APP_KEY, so a
     * reference cannot be guessed from the two ids in front of it.
     */
    public static function buildReference(User $user, Category $category, Carbon $completedAt): string
    {
        $fingerprint = hash_hmac(
            'sha256',
            implode('|', [$user->id, $category->id, $completedAt->toDateString()]),
            (string) config('app.key'),
        );

        return sprintf(
            'TPA-%02d-%05d-%s',
            $category->id,
            $user->id,
            Str::upper(substr($fingerprint, 0, 8)),
        );
    }
}

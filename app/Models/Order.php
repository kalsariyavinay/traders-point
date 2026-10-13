<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'video_id',
        'coupon_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'amount',
        'discount_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'status' => OrderStatus::class,
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
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopeSuccessful(Builder $query): void
    {
        $query->where('status', OrderStatus::Success);
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Success;
    }

    /**
     * Razorpay works in the smallest currency unit (paise).
     */
    public function amountInPaise(): int
    {
        return (int) round((float) $this->amount * 100);
    }
}

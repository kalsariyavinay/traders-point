<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'discount_percent' => 25,
            'is_active' => true,
            'max_uses' => null,
            'used_count' => 0,
            'expires_at' => null,
        ];
    }

    public function percent(int $percent): static
    {
        return $this->state(fn (array $attributes) => ['discount_percent' => $percent]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subDay()]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => ['max_uses' => 5, 'used_count' => 5]);
    }
}

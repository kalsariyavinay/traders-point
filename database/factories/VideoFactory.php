<?php

namespace Database\Factories;

use App\Enums\VideoStatus;
use App\Models\Category;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'category_id' => Category::factory(),
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'thumbnail' => 'thumbnails/placeholder.jpg',
            'video_path' => 'videos/placeholder.mp4',
            // Fixed so the 90% completion threshold is predictable in tests.
            'duration_seconds' => 600,
            'price' => 0.00,
            'is_free' => true,
            'status' => VideoStatus::Published,
        ];
    }

    /**
     * A paid video at the given price.
     */
    public function paid(float $price = 499.00): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free' => false,
            'price' => $price,
        ]);
    }

    /** A lesson whose length the admin never filled in: cannot be completed. */
    public function withoutDuration(): static
    {
        return $this->state(fn (array $attributes) => ['duration_seconds' => null]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoStatus::Draft,
        ]);
    }
}

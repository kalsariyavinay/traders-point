<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'reference' => 'TPA-01-00001-'.Str::upper(Str::random(8)),
            'lessons_count' => 2,
            'completed_at' => now(),
        ];
    }
}

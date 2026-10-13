<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Services\RazorpayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The two endpoints a script could hammer: one writes order rows and calls
 * Razorpay, the other writes on every beacon.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_allows_ten_attempts_a_minute(): void
    {
        config(['services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => 'test_secret']);

        $this->mock(RazorpayGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('key')->andReturn('rzp_test_key');
            $mock->shouldReceive('currency')->andReturn('INR');
            $mock->shouldReceive('createOrder')->andReturn('order_ABC123');
        });

        $user = User::factory()->create();
        $video = Video::factory()->paid()->create();

        foreach (range(1, 10) as $ignored) {
            $this->actingAs($user)->postJson(route('razorpay.order', $video))->assertOk();
        }

        $this->actingAs($user)->postJson(route('razorpay.order', $video))->assertStatus(429);
    }

    public function test_the_progress_beacon_allows_sixty_pings_a_minute(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('videos/lesson.mp4', 'fake-video-bytes');

        $user = User::factory()->create();
        $video = Video::factory()->create(['video_path' => 'videos/lesson.mp4']);

        foreach (range(1, 60) as $second) {
            $this->actingAs($user)
                ->postJson(route('videos.progress', $video), ['progress_seconds' => $second])
                ->assertNoContent();
        }

        $this->actingAs($user)
            ->postJson(route('videos.progress', $video), ['progress_seconds' => 61])
            ->assertStatus(429);
    }

    public function test_the_limit_is_per_user_not_global(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('videos/lesson.mp4', 'fake-video-bytes');

        $video = Video::factory()->create(['video_path' => 'videos/lesson.mp4']);
        $noisy = User::factory()->create();
        $quiet = User::factory()->create();

        foreach (range(1, 60) as $second) {
            $this->actingAs($noisy)->postJson(route('videos.progress', $video), ['progress_seconds' => $second]);
        }

        $this->actingAs($noisy)
            ->postJson(route('videos.progress', $video), ['progress_seconds' => 61])
            ->assertStatus(429);

        // Somebody else watching the same lesson is unaffected.
        $this->actingAs($quiet)
            ->postJson(route('videos.progress', $video), ['progress_seconds' => 5])
            ->assertNoContent();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\VideoStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_videos_are_open_to_guests(): void
    {
        $video = Video::factory()->create();

        $this->assertTrue($video->isAccessibleBy(null));
    }

    public function test_paid_videos_are_closed_until_purchased(): void
    {
        $video = Video::factory()->paid()->create();
        $user = User::factory()->create();

        $this->assertFalse($video->isAccessibleBy(null));
        $this->assertFalse($video->isAccessibleBy($user));

        $user->purchasedVideos()->attach($video);

        $this->assertTrue($video->fresh()->isAccessibleBy($user->fresh()));
    }

    public function test_admins_can_access_paid_videos(): void
    {
        $video = Video::factory()->paid()->create();

        $this->assertTrue($video->isAccessibleBy(User::factory()->admin()->create()));
    }

    public function test_a_user_cannot_own_the_same_video_twice(): void
    {
        $video = Video::factory()->paid()->create();
        $user = User::factory()->create();

        $user->purchasedVideos()->attach($video);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $user->purchasedVideos()->attach($video);
    }

    public function test_published_and_paid_scopes_filter_videos(): void
    {
        Video::factory()->create();
        Video::factory()->draft()->create();
        Video::factory()->paid()->create();

        $this->assertCount(2, Video::published()->get());
        $this->assertCount(1, Video::paid()->get());
        $this->assertCount(2, Video::free()->get());
    }

    public function test_order_relations_and_paise_conversion(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->paid(499.00)->create();

        $order = Order::create([
            'user_id' => $user->id,
            'video_id' => $video->id,
            'amount' => $video->price,
            'status' => OrderStatus::Pending,
        ]);

        $this->assertSame($user->id, $order->user->id);
        $this->assertSame($video->id, $order->video->id);
        $this->assertSame(49900, $order->amountInPaise());
        $this->assertFalse($order->isPaid());

        $order->update(['status' => OrderStatus::Success]);

        $this->assertTrue($order->fresh()->isPaid());
        $this->assertCount(1, Order::successful()->get());
        $this->assertSame(VideoStatus::Published, $video->status);
    }
}

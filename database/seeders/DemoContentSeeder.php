<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\VideoStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Optional sample catalogue for poking at the admin panel. Not part of
 * DatabaseSeeder -- run it on demand:
 *
 *     php artisan db:seed --class=DemoContentSeeder
 *
 * Every video points at an external URL, so nothing needs to be uploaded.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        // [title, price, is_free, status, duration in seconds]
        $catalogue = [
            'basics-of-stock-market' => [
                ['How the Stock Market Works', 0, true, VideoStatus::Published, 720],
                ['Opening Your First Demat Account', 0, true, VideoStatus::Published, 540],
            ],
            'technical-analysis' => [
                ['Candlestick Patterns Masterclass', 1499.00, false, VideoStatus::Published, 2100],
                ['Support, Resistance and Trendlines', 999.00, false, VideoStatus::Published, 1680],
                ['Volume Profile Deep Dive', 1299.00, false, VideoStatus::Draft, 1920],
            ],
            'options-trading' => [
                ['Options Greeks Explained', 1999.00, false, VideoStatus::Published, 2640],
                ['Iron Condor Setups', 2499.00, false, VideoStatus::Draft, 1500],
            ],
        ];

        foreach ($catalogue as $categorySlug => $videos) {
            $category = Category::where('slug', $categorySlug)->sole();

            foreach ($videos as [$title, $price, $isFree, $status, $duration]) {
                $video = Video::firstOrCreate(
                    ['slug' => Str::slug($title)],
                    [
                        'category_id' => $category->id,
                        'title' => $title,
                        'description' => "Sample lesson: {$title}.",
                        // RFC 2606 reserves example.com, so a demo row can never be
                        // mistaken for real content or quietly half-work. The admin
                        // list flags these; each needs a real link before it plays.
                        'video_path' => 'https://player.example.com/embed/'.Str::slug($title),
                        'duration_seconds' => $duration,
                        'price' => $price,
                        'is_free' => $isFree,
                        'status' => $status,
                    ],
                );

                // Fill a length for demo rows made before the column existed,
                // without overwriting anything the admin has since set.
                if ($video->duration_seconds === null) {
                    $video->update(['duration_seconds' => $duration]);
                }
            }
        }

        // A coupon to try the discounted checkout with.
        Coupon::firstOrCreate(
            ['code' => 'DIWALI25'],
            ['discount_percent' => 25, 'max_uses' => 100, 'expires_at' => now()->addMonths(3)],
        );

        $buyer = User::where('email', 'test@example.com')->first();

        if (! $buyer) {
            return;
        }

        // One paid purchase so the dashboard and orders table have something to show.
        $purchased = Video::published()->paid()->first();

        if ($purchased) {
            Order::firstOrCreate(
                ['razorpay_order_id' => 'order_demo_'.$purchased->id],
                [
                    'user_id' => $buyer->id,
                    'video_id' => $purchased->id,
                    'razorpay_payment_id' => 'pay_demo_'.$purchased->id,
                    'amount' => $purchased->price,
                    'status' => OrderStatus::Success,
                ],
            );

            $buyer->purchasedVideos()->syncWithoutDetaching([$purchased->id]);
        }

        // Finish the free Basics lessons so the completion badges and the
        // category certificate are visible straight away.
        Video::published()
            ->free()
            ->whereHas('category', fn ($q) => $q->where('slug', 'basics-of-stock-market'))
            ->get()
            ->each(fn (Video $video) => $buyer->fresh()->recordProgress($video, (int) $video->duration_seconds));

        // ...and one failed attempt, so the status filter is worth clicking.
        $failedOn = Video::published()->paid()->skip(1)->first();

        if ($failedOn) {
            Order::firstOrCreate(
                ['razorpay_order_id' => 'order_demo_failed_'.$failedOn->id],
                [
                    'user_id' => $buyer->id,
                    'video_id' => $failedOn->id,
                    'amount' => $failedOn->price,
                    'status' => OrderStatus::Failed,
                ],
            );
        }
    }
}

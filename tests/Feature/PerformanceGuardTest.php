<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guards for work that is easy to undo by accident. An N+1 or a full-size
 * image costs nothing in correctness, so nothing else in the suite notices.
 */
class PerformanceGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function queriesWhile(callable $work): array
    {
        $queries = [];

        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        $work();

        return $queries;
    }

    public function test_the_related_rail_does_not_query_per_card(): void
    {
        $category = Category::factory()->create();
        $video = Video::factory()->for($category)->create();
        Video::factory()->count(3)->for($category)->create();

        $viewer = User::factory()->create();

        $queries = $this->queriesWhile(function () use ($viewer, $video) {
            $this->actingAs($viewer)->get(route('videos.show', $video))->assertOk();
        });

        // Each related card used to cost an ownership EXISTS plus a category
        // lookup. Both are now resolved once for the whole rail.
        $perCard = collect($queries)->filter(
            fn (string $sql) => str_contains($sql, 'video_user') || str_contains($sql, 'from "categories"')
        );

        // 3: the page's own category load, the eager-load for the rail, and one
        // ownership lookup for the whole rail. The guard that really matters is
        // the next test -- this number must not grow with the card count.
        $this->assertLessThanOrEqual(
            3,
            $perCard->count(),
            "The related rail is querying per card again:\n".$perCard->implode("\n")
        );
    }

    public function test_adding_more_related_lessons_does_not_add_queries(): void
    {
        $category = Category::factory()->create();
        $video = Video::factory()->for($category)->create();
        $viewer = User::factory()->create();

        Video::factory()->count(1)->for($category)->create();
        $withOne = count($this->queriesWhile(function () use ($viewer, $video) {
            $this->actingAs($viewer)->get(route('videos.show', $video))->assertOk();
        }));

        Video::factory()->count(2)->for($category)->create();
        $withThree = count($this->queriesWhile(function () use ($viewer, $video) {
            $this->actingAs($viewer)->get(route('videos.show', $video))->assertOk();
        }));

        $this->assertSame(
            $withOne,
            $withThree,
            "Query count grew from {$withOne} to {$withThree} when the rail went from 1 card to 3."
        );
    }

    public function test_an_oversized_thumbnail_is_downscaled_on_upload(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('admin.videos.store'), [
            'title' => 'A Lesson With A Huge Thumbnail',
            'category_id' => $category->id,
            'status' => 'published',
            'is_free' => '1',
            'video_source' => 'link',
            'video_url' => 'https://www.youtube.com/embed/AkMsMDk_brU',
            'thumbnail' => UploadedFile::fake()->image('poster.jpg', 2560, 1440),
        ])->assertSessionHasNoErrors();

        $stored = Video::sole()->thumbnail;
        [$width] = getimagesizefromstring(Storage::disk('public')->get($stored));

        $this->assertSame(1280, $width, 'The thumbnail was stored at its original width.');
    }

    public function test_a_thumbnail_that_is_already_small_is_left_exactly_as_it_was(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('admin.videos.store'), [
            'title' => 'A Lesson With A Small Thumbnail',
            'category_id' => $category->id,
            'status' => 'published',
            'is_free' => '1',
            'video_source' => 'link',
            'video_url' => 'https://www.youtube.com/embed/AkMsMDk_brU',
            'thumbnail' => UploadedFile::fake()->image('poster.jpg', 640, 360),
        ])->assertSessionHasNoErrors();

        [$width, $height] = getimagesizefromstring(
            Storage::disk('public')->get(Video::sole()->thumbnail)
        );

        $this->assertSame([640, 360], [$width, $height]);
    }

    public function test_the_shipped_javascript_bundle_stays_lean(): void
    {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $entry = $manifest['resources/js/app.js']['file'] ?? null;

        $this->assertNotNull($entry, 'No built JS entry in the Vite manifest — run npm run build.');

        $bytes = filesize(public_path("build/{$entry}"));

        // It was 113 KB while axios rode along unused. Alpine plus this app's
        // own components is about 62 KB; 80 KB leaves room without hiding a
        // whole library creeping back in.
        $this->assertLessThan(
            80 * 1024,
            $bytes,
            'The JS bundle is '.round($bytes / 1024).' KB. Something large was added — check for an unused dependency.'
        );
    }
}

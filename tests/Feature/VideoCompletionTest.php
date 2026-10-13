<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Completion is judged on the server from the admin-entered duration. The
 * browser only ever reports a playhead position.
 */
class VideoCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function lesson(int $duration = 600): Video
    {
        Storage::disk('local')->put('videos/lesson.mp4', 'fake-video-bytes');

        return Video::factory()->create([
            'video_path' => 'videos/lesson.mp4',
            'duration_seconds' => $duration,
        ]);
    }

    public function test_the_threshold_is_ninety_percent_of_the_duration(): void
    {
        $this->assertSame(540, $this->lesson(600)->completionThreshold());
        $this->assertSame(90, Video::factory()->make(['duration_seconds' => 100])->completionThreshold());
        $this->assertNull(Video::factory()->make(['duration_seconds' => null])->completionThreshold());
    }

    public function test_stopping_below_ninety_percent_does_not_complete_the_lesson(): void
    {
        $user = User::factory()->create();
        $video = $this->lesson(600);

        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 539])
            ->assertNoContent();

        $this->assertFalse($user->fresh()->hasCompleted($video));
        $this->assertDatabaseHas('video_views', ['video_id' => $video->id, 'completed_at' => null]);
    }

    public function test_reaching_ninety_percent_completes_the_lesson(): void
    {
        $user = User::factory()->create();
        $video = $this->lesson(600);

        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 540])
            ->assertNoContent();

        $this->assertTrue($user->fresh()->hasCompleted($video));
    }

    public function test_a_lesson_with_no_duration_can_never_complete(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->withoutDuration()->create();

        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 86400]);

        $this->assertFalse($user->fresh()->hasCompleted($video));
    }

    public function test_a_forged_beacon_cannot_outrun_the_duration(): void
    {
        $user = User::factory()->create();
        $video = $this->lesson(600);

        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 86400]);

        // Clamped to the real length -- which here is itself past the threshold,
        // but the stored playhead can never exceed the lesson.
        $this->assertSame(600, $user->fresh()->progressFor($video));
    }

    public function test_completion_is_sticky_when_the_lesson_is_restarted(): void
    {
        $user = User::factory()->create();
        $video = $this->lesson(600);

        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 600]);
        $completedAt = $user->fresh()->watchedVideos()->first()->pivot->completed_at;

        // Watching again from the top must not undo the badge.
        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 12]);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasCompleted($video));
        $this->assertSame(12, $fresh->progressFor($video));
        $this->assertSame((string) $completedAt, (string) $fresh->watchedVideos()->first()->pivot->completed_at);
    }

    public function test_a_completed_lesson_resumes_from_the_start(): void
    {
        $user = User::factory()->create();
        $video = $this->lesson(600);

        $user->recordProgress($video, 600);

        $this->assertSame(0, $user->fresh()->resumePositionFor($video));

        $this->actingAs($user)->get(route('videos.show', $video))->assertViewHas('resumeAt', 0);
    }

    public function test_an_unfinished_lesson_still_resumes_where_it_stopped(): void
    {
        $user = User::factory()->create();
        $video = $this->lesson(600);

        $user->recordProgress($video, 300);

        $this->assertSame(300, $user->fresh()->resumePositionFor($video));
    }

    public function test_the_dashboard_shows_a_completion_badge(): void
    {
        $user = User::factory()->create();
        $video = $this->lesson(600);

        $user->recordProgress($video, 600);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Completed')
            ->assertSee('Watch again')
            ->assertViewHas('completedCount', 1)
            ->assertDontSee('Stopped at');
    }

    public function test_the_admin_sets_the_duration(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('admin.videos.store'), [
            'title' => 'Timed Lesson',
            'category_id' => $category->id,
            'status' => 'published',
            'is_free' => '1',
            'duration_seconds' => '1800',
            'video_source' => 'link',
            'video_url' => 'https://player.example.com/embed/timed',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1800, Video::sole()->duration_seconds);
    }

    public function test_the_duration_must_be_a_sane_number(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        foreach (['0', '-60', 'ten minutes', '90000'] as $bad) {
            $this->actingAs($admin)->post(route('admin.videos.store'), [
                'title' => 'Timed Lesson',
                'category_id' => $category->id,
                'status' => 'published',
                'is_free' => '1',
                'duration_seconds' => $bad,
                'video_source' => 'link',
                'video_url' => 'https://player.example.com/embed/timed',
            ])->assertSessionHasErrors('duration_seconds');
        }

        $this->assertDatabaseEmpty('videos');
    }

    public function test_category_completion_needs_every_published_lesson(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $one = Video::factory()->for($category)->create(['duration_seconds' => 100]);
        $two = Video::factory()->for($category)->create(['duration_seconds' => 100]);
        // A draft must not block the certificate.
        Video::factory()->for($category)->draft()->create(['duration_seconds' => 100]);

        $user->recordProgress($one, 100);
        $this->assertFalse($user->fresh()->hasCompletedCategory($category));

        $user->fresh()->recordProgress($two, 100);
        $this->assertTrue($user->fresh()->hasCompletedCategory($category));
    }

    public function test_an_empty_category_is_never_complete(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->assertFalse($user->hasCompletedCategory($category));
    }
}

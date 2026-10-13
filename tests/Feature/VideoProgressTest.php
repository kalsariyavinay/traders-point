<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function uploadedVideo(bool $free = true): Video
    {
        Storage::disk('local')->put('videos/lesson.mp4', 'fake-video-bytes');

        $factory = Video::factory();

        return ($free ? $factory : $factory->paid())->create(['video_path' => 'videos/lesson.mp4']);
    }

    public function test_a_guest_cannot_record_progress(): void
    {
        $video = $this->uploadedVideo();

        $this->post(route('videos.progress', $video), ['progress_seconds' => 42])
            ->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('video_views');
    }

    public function test_it_stores_the_playhead_and_answers_204(): void
    {
        $user = User::factory()->create();
        $video = $this->uploadedVideo();

        $this->actingAs($user)
            ->postJson(route('videos.progress', $video), ['progress_seconds' => 95])
            ->assertNoContent();

        $this->assertDatabaseHas('video_views', [
            'user_id' => $user->id,
            'video_id' => $video->id,
            'progress_seconds' => 95,
        ]);

        $this->assertSame(95, $user->fresh()->progressFor($video));
    }

    public function test_a_later_ping_overwrites_the_earlier_one(): void
    {
        $user = User::factory()->create();
        $video = $this->uploadedVideo();

        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 30]);
        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 120]);

        $this->assertDatabaseCount('video_views', 1);
        $this->assertSame(120, $user->fresh()->progressFor($video));
    }

    public function test_finishing_a_video_clears_the_mark(): void
    {
        $user = User::factory()->create();
        $video = $this->uploadedVideo();

        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 540]);

        // The player sends 0 on `ended`, so the next visit starts at the top.
        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 0]);

        $this->assertSame(0, $user->fresh()->progressFor($video));
    }

    public function test_progress_on_a_locked_video_is_refused(): void
    {
        $user = User::factory()->create();
        $video = $this->uploadedVideo(free: false);

        $this->actingAs($user)
            ->postJson(route('videos.progress', $video), ['progress_seconds' => 42])
            ->assertForbidden();

        $this->assertDatabaseEmpty('video_views');
    }

    public function test_a_buyer_can_record_progress_on_a_paid_video(): void
    {
        $user = User::factory()->create();
        $video = $this->uploadedVideo(free: false);
        $user->purchasedVideos()->attach($video);

        $this->actingAs($user)
            ->postJson(route('videos.progress', $video), ['progress_seconds' => 60])
            ->assertNoContent();

        $this->assertSame(60, $user->fresh()->progressFor($video));
    }

    public function test_a_draft_is_hidden_from_the_beacon(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->draft()->create();

        $this->actingAs($user)
            ->postJson(route('videos.progress', $video), ['progress_seconds' => 10])
            ->assertNotFound();
    }

    public function test_the_payload_is_validated(): void
    {
        $user = User::factory()->create();
        $video = $this->uploadedVideo();

        $this->actingAs($user)->postJson(route('videos.progress', $video), [])
            ->assertJsonValidationErrors('progress_seconds');

        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => -5])
            ->assertJsonValidationErrors('progress_seconds');

        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 999999])
            ->assertJsonValidationErrors('progress_seconds');

        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 'halfway'])
            ->assertJsonValidationErrors('progress_seconds');

        $this->assertDatabaseEmpty('video_views');
    }

    public function test_recording_progress_is_not_a_purchase(): void
    {
        $user = User::factory()->create();
        $video = $this->uploadedVideo();

        $this->actingAs($user)->postJson(route('videos.progress', $video), ['progress_seconds' => 42]);

        $this->assertDatabaseEmpty('video_user');
        $this->assertFalse($user->fresh()->hasPurchased($video));
    }

    public function test_the_player_is_told_where_to_resume(): void
    {
        $user = User::factory()->create();
        $video = $this->uploadedVideo();

        $user->recordProgress($video, 185);

        $this->actingAs($user)
            ->get(route('videos.show', $video))
            ->assertOk()
            ->assertViewHas('resumeAt', 185)
            ->assertSee('videoProgress(', false)
            ->assertSee('resumeAt: 185', false)
            ->assertSee('x-ref="player"', false);
    }

    public function test_an_external_embed_gets_no_progress_tracking(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->create(['video_path' => 'https://player.example.com/embed/abc']);

        $this->actingAs($user)
            ->get(route('videos.show', $video))
            ->assertOk()
            ->assertDontSee('videoProgress(', false);
    }

    public function test_a_guest_viewing_a_free_video_gets_no_tracking(): void
    {
        $video = $this->uploadedVideo();

        // There is nobody to save a position against, so the player stays plain
        // rather than firing beacons that would only bounce off the auth wall.
        $this->get(route('videos.show', $video))
            ->assertOk()
            ->assertViewHas('resumeAt', 0)
            ->assertDontSee('videoProgress(', false);
    }

    public function test_the_dashboard_offers_to_resume(): void
    {
        $user = User::factory()->create();
        $video = $this->uploadedVideo();

        $user->recordProgress($video, 125);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Stopped at 2:05')
            ->assertSee('Resume');
    }

    public function test_the_dashboard_says_watch_when_there_is_nothing_to_resume(): void
    {
        $user = User::factory()->create();
        $video = $this->uploadedVideo();

        $user->recordWatch($video);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Stopped at')
            ->assertSee('Watch');
    }
}

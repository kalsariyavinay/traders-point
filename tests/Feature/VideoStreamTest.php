<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Support\StreamToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Entitlement: who is allowed to read an uploaded lesson at all.
 *
 * The signing and binding rules that sit on top live in
 * VideoStreamSecurityTest.
 */
class VideoStreamTest extends TestCase
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

        return ($free ? $factory : $factory->paid())->create([
            'video_path' => 'videos/lesson.mp4',
        ]);
    }

    /** The per-session secret the link is bound to, seeded for the request. */
    private const KEY = 'test-session-key';

    /**
     * A link minted for whoever this test is currently acting as.
     *
     * withSession() puts the same key into the session the request will carry,
     * so mint and check agree -- the test client starts a fresh session per
     * request, which is why the binding cannot be the session id.
     */
    private function linkFor(Video $video, ?User $user = null): string
    {
        $this->withSession([StreamToken::sessionKeyName() => self::KEY]);

        return StreamToken::url($video, $user, self::KEY);
    }

    public function test_a_guest_can_stream_a_free_video(): void
    {
        $video = $this->uploadedVideo();

        $this->get($this->linkFor($video))
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_a_guest_cannot_stream_a_paid_video(): void
    {
        $video = $this->uploadedVideo(free: false);

        $this->get($this->linkFor($video))->assertForbidden();
    }

    public function test_a_signed_in_non_buyer_cannot_stream_a_paid_video(): void
    {
        $user = User::factory()->create();
        $video = $this->uploadedVideo(free: false);

        $this->actingAs($user)->get($this->linkFor($video, $user))->assertForbidden();
    }

    public function test_a_buyer_can_stream_a_paid_video(): void
    {
        $user = User::factory()->create();
        $video = $this->uploadedVideo(free: false);

        $user->purchasedVideos()->attach($video);

        $this->actingAs($user)->get($this->linkFor($video, $user))->assertStatus(206);
    }

    public function test_an_admin_can_stream_a_paid_video(): void
    {
        $admin = User::factory()->admin()->create();
        $video = $this->uploadedVideo(free: false);

        $this->actingAs($admin)->get($this->linkFor($video, $admin))->assertStatus(206);
    }

    public function test_range_requests_are_answered_so_seeking_works(): void
    {
        $video = $this->uploadedVideo();

        $this->get($this->linkFor($video), ['Range' => 'bytes=0-4'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-4/16');
    }

    public function test_a_range_past_the_end_of_the_file_is_refused(): void
    {
        $video = $this->uploadedVideo();

        $this->get($this->linkFor($video), ['Range' => 'bytes=9999-'])
            ->assertStatus(416)
            ->assertHeader('Content-Range', 'bytes */16');
    }

    public function test_an_external_video_is_never_proxied(): void
    {
        $video = Video::factory()->create([
            'video_path' => 'https://player.example.com/embed/abc',
        ]);

        $this->get($this->linkFor($video))->assertNotFound();
    }

    public function test_a_missing_file_is_a_404_rather_than_an_error(): void
    {
        $video = Video::factory()->create(['video_path' => 'videos/gone.mp4']);

        $this->get($this->linkFor($video))->assertNotFound();
    }

    public function test_a_draft_cannot_be_streamed_by_the_public(): void
    {
        Storage::disk('local')->put('videos/lesson.mp4', 'fake-video-bytes');
        $video = Video::factory()->draft()->create(['video_path' => 'videos/lesson.mp4']);

        $this->get($this->linkFor($video))->assertNotFound();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get($this->linkFor($video, $admin))->assertStatus(206);
    }
}

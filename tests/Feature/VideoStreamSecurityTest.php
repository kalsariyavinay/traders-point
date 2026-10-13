<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Support\StreamToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The signing, binding and slicing rules on top of plain entitlement.
 *
 * Entitlement itself (free / paid / draft / admin) is VideoStreamTest.
 */
class VideoStreamSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'viewer-one-key';

    private const OTHER_KEY = 'viewer-two-key';

    private User $buyer;

    private Video $video;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::disk('local')->put('videos/lesson.mp4', str_repeat('A', 5_000_000));

        $this->buyer = User::factory()->create();
        $this->video = Video::factory()->paid(999.00)->create(['video_path' => 'videos/lesson.mp4']);
        $this->buyer->purchasedVideos()->attach($this->video);
    }

    private function seeded(string $key = self::KEY): static
    {
        $this->withSession([StreamToken::sessionKeyName() => $key]);

        return $this;
    }

    private function link(?User $user = null, string $key = self::KEY): string
    {
        return StreamToken::url($this->video, $user ?? $this->buyer, $key);
    }

    // ----------------------------------------------------------- the happy path

    public function test_an_entitled_viewer_with_a_fresh_link_can_stream(): void
    {
        $this->seeded()->actingAs($this->buyer)
            ->get($this->link(), ['Range' => 'bytes=0-1023'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-1023/5000000')
            ->assertHeader('Content-Length', '1024')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_page_hands_the_player_a_signed_link_not_a_bare_path(): void
    {
        $free = Video::factory()->create(['video_path' => 'videos/lesson.mp4']);

        $html = $this->actingAs($this->buyer)->get(route('videos.show', $free))->assertOk()->getContent();

        preg_match('/<source src="([^"]+)"/', $html, $m);
        $src = html_entity_decode($m[1] ?? '');

        $this->assertStringContainsString('/stream/', $src);
        $this->assertStringContainsString('expires=', $src);
        $this->assertStringContainsString('signature=', $src);
        // The old guessable path must not come back.
        $this->assertStringNotContainsString('/stream', str_replace('/stream/', '', $src));
    }

    // --------------------------------------------------------------- signatures

    public function test_a_tampered_signature_is_refused(): void
    {
        $url = $this->link();
        $forged = preg_replace('/signature=[a-f0-9]+/', 'signature='.str_repeat('a', 64), $url);

        $this->seeded()->actingAs($this->buyer)->get($forged)->assertForbidden();
    }

    /**
     * The hole a live run found and the unit tests did not.
     *
     * Once a session has legitimately started a lesson it holds a grant, and
     * an earlier version let that grant carry a request whose signature was
     * forged -- because it only asked "is the signature valid AND fresh?" and
     * fell through to the grant on any no. Tampering must be fatal whatever
     * the session has earned, so this test tampers AFTER a real stream.
     */
    public function test_a_tampered_signature_is_refused_even_after_a_legitimate_stream(): void
    {
        $url = $this->link();

        $this->seeded()->actingAs($this->buyer)
            ->get($url, ['Range' => 'bytes=0-1023'])
            ->assertStatus(206);

        $grant = session("stream_grants.{$this->video->id}");
        $this->assertNotNull($grant, 'Precondition failed: no grant, so this proves nothing.');

        $forged = preg_replace('/signature=[a-f0-9]+/', 'signature='.str_repeat('b', 64), $url);

        $this->withSession([
            StreamToken::sessionKeyName() => self::KEY,
            "stream_grants.{$this->video->id}" => $grant,
        ])->actingAs($this->buyer)
            ->get($forged)
            ->assertForbidden();
    }

    public function test_a_link_with_no_signature_at_all_is_refused(): void
    {
        $bare = strtok($this->link(), '?');

        $this->seeded()->actingAs($this->buyer)->get($bare)->assertForbidden();
    }

    public function test_stretching_the_expiry_invalidates_the_signature(): void
    {
        // expires is inside the signed payload, so editing it breaks the HMAC.
        $url = preg_replace('/expires=\d+/', 'expires='.(time() + 86400), $this->link());

        $this->seeded()->actingAs($this->buyer)->get($url)->assertForbidden();
    }

    public function test_an_expired_link_is_refused(): void
    {
        $url = $this->link();

        $this->travel(StreamToken::ttl() + 5)->seconds();

        $this->seeded()->actingAs($this->buyer)->get($url)->assertForbidden();
    }

    public function test_a_link_still_works_at_the_edge_of_its_window(): void
    {
        $url = $this->link();

        $this->travel(StreamToken::ttl() - 5)->seconds();

        $this->seeded()->actingAs($this->buyer)->get($url)->assertStatus(206);
    }

    // ------------------------------------------------------- user & session binding

    public function test_a_link_leaked_to_another_session_is_refused(): void
    {
        // Same buyer, same entitlement, different browser. The signature is
        // still perfectly valid; the binding is what refuses it.
        $url = $this->link();

        $this->seeded(self::OTHER_KEY)->actingAs($this->buyer)->get($url)->assertForbidden();
    }

    public function test_a_link_leaked_to_another_user_is_refused(): void
    {
        $otherBuyer = User::factory()->create();
        $otherBuyer->purchasedVideos()->attach($this->video);

        // The thief has paid for this very lesson, so entitlement passes --
        // only the binding stops them replaying someone else's link.
        $this->seeded()->actingAs($otherBuyer)->get($this->link())->assertForbidden();
    }

    public function test_a_guests_link_does_not_work_once_signed_in(): void
    {
        $free = Video::factory()->create(['video_path' => 'videos/lesson.mp4']);
        $asGuest = StreamToken::url($free, null, self::KEY);

        $this->seeded()->get($asGuest)->assertStatus(206);
        $this->seeded()->actingAs($this->buyer)->get($asGuest)->assertForbidden();
    }

    public function test_a_token_for_one_lesson_cannot_be_replayed_on_another(): void
    {
        $other = Video::factory()->paid(499.00)->create(['video_path' => 'videos/lesson.mp4']);
        $this->buyer->purchasedVideos()->attach($other);

        $token = StreamToken::for($this->video, $this->buyer, self::KEY);
        $swapped = StreamToken::url($other, $this->buyer, self::KEY);
        $swapped = preg_replace('#/stream/[^/]+/[a-f0-9]+#', "/stream/{$other->slug}/{$token}", $swapped);

        $this->seeded()->actingAs($this->buyer)->get($swapped)->assertForbidden();
    }

    // ------------------------------------------------- continuing past the window

    public function test_playback_survives_past_the_link_window_in_the_same_session(): void
    {
        /*
         * The reason the grant exists. A <video> keeps asking for bytes for as
         * long as the lesson plays, so a 60-second link would cut every lesson
         * off after a minute if the first valid request did not earn a longer
         * window.
         */
        $this->video->update(['duration_seconds' => 1200]);
        $url = $this->link();

        $session = $this->seeded()->actingAs($this->buyer);
        $session->get($url, ['Range' => 'bytes=0-1023'])->assertStatus(206);

        $grant = session()->all()["stream_grants.{$this->video->id}"]
            ?? session("stream_grants.{$this->video->id}");

        $this->assertNotNull($grant, 'No grant was recorded for this session.');

        $this->travel(StreamToken::ttl() + 60)->seconds();

        $this->withSession([
            StreamToken::sessionKeyName() => self::KEY,
            "stream_grants.{$this->video->id}" => $grant,
        ])->actingAs($this->buyer)
            ->get($url, ['Range' => 'bytes=2048-3071'])
            ->assertStatus(206);
    }

    public function test_a_grant_does_not_help_a_different_session(): void
    {
        $this->travel(StreamToken::ttl() + 60)->seconds();

        // Someone else's grant, replayed with someone else's link.
        $this->withSession([
            StreamToken::sessionKeyName() => self::OTHER_KEY,
            "stream_grants.{$this->video->id}" => now()->addHour()->getTimestamp(),
        ])->actingAs($this->buyer)
            ->get($this->link())
            ->assertForbidden();
    }

    public function test_an_expired_grant_stops_the_stream(): void
    {
        $url = $this->link();

        $this->travel(StreamToken::ttl() + 60)->seconds();

        $this->withSession([
            StreamToken::sessionKeyName() => self::KEY,
            "stream_grants.{$this->video->id}" => now()->subMinute()->getTimestamp(),
        ])->actingAs($this->buyer)
            ->get($url)
            ->assertForbidden();
    }

    // ------------------------------------------------------- no whole-file pulls

    public function test_a_request_for_the_whole_file_only_yields_one_slice(): void
    {
        $chunk = (int) config('academy.streaming.chunk_bytes');

        $response = $this->seeded()->actingAs($this->buyer)
            ->get($this->link(), ['Range' => 'bytes=0-'])
            ->assertStatus(206);

        $this->assertSame((string) $chunk, $response->headers->get('Content-Length'));
        $this->assertSame('bytes 0-'.($chunk - 1).'/5000000', $response->headers->get('Content-Range'));
    }

    public function test_a_download_manager_sending_no_range_header_gets_a_slice_too(): void
    {
        $chunk = (int) config('academy.streaming.chunk_bytes');

        $response = $this->seeded()->actingAs($this->buyer)->get($this->link())->assertStatus(206);

        $this->assertSame((string) $chunk, $response->headers->get('Content-Length'));
        $this->assertLessThan(5_000_000, (int) $response->headers->get('Content-Length'));
    }

    public function test_the_response_is_never_cacheable(): void
    {
        $response = $this->seeded()->actingAs($this->buyer)->get($this->link());

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertSame('inline', $response->headers->get('Content-Disposition'));
    }

    // -------------------------------------------------------------- the player

    public function test_the_player_markup_blocks_the_obvious_download_routes(): void
    {
        $free = Video::factory()->create(['video_path' => 'videos/lesson.mp4']);

        $this->actingAs($this->buyer)
            ->get(route('videos.show', $free))
            ->assertOk()
            ->assertSee('controlslist="nodownload noremoteplayback noplaybackrate"', false)
            ->assertSee('disablepictureinpicture', false)
            ->assertSee('x-data="playerGuard"', false)
            ->assertSee('x-on:contextmenu.prevent', false);
    }
}

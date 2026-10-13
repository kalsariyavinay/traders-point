<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How a stored video_path turns into something that actually plays.
 *
 * The bug this guards against: an admin pasted the YouTube link from their
 * address bar, the save succeeded, and the lesson page showed a black box
 * forever, because a /watch page refuses to be framed.
 */
class VideoPlaybackTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pasted_youtube_link_is_framed_in_its_embeddable_form(): void
    {
        $video = Video::factory()->create([
            'video_path' => 'https://www.youtube.com/watch?v=AkMsMDk_brU',
        ]);

        $this->get(route('videos.show', $video))
            ->assertOk()
            ->assertSee('<iframe src="https://www.youtube.com/embed/AkMsMDk_brU"', false)
            ->assertDontSee('<iframe src="https://www.youtube.com/watch', false);
    }

    public function test_a_blocked_embed_still_offers_a_way_to_watch_the_lesson(): void
    {
        $video = Video::factory()->create([
            'video_path' => 'https://www.youtube.com/watch?v=AkMsMDk_brU',
        ]);

        // Three different causes -- a page URL, a dead host, a deleted video --
        // all look like the same empty rectangle, so the original link is the
        // only thing that can rescue the viewer.
        $this->get(route('videos.show', $video))
            ->assertOk()
            ->assertSee('Player not loading?')
            ->assertSee('href="https://www.youtube.com/watch?v=AkMsMDk_brU"', false);
    }

    public function test_a_locked_video_offers_no_player_and_no_source_link(): void
    {
        $video = Video::factory()->paid(999.00)->create([
            'video_path' => 'https://www.youtube.com/watch?v=AkMsMDk_brU',
        ]);

        $this->get(route('videos.show', $video))
            ->assertOk()
            ->assertSee('This lesson is paid')
            ->assertDontSee('Player not loading?')
            ->assertDontSee('AkMsMDk_brU');
    }

    public function test_a_remote_video_file_plays_in_our_own_player_and_records_progress(): void
    {
        // A CDN mp4 is a reasonable thing to paste, and the cheapest way to
        // avoid the upload path. In an iframe it loses the poster, the
        // download guard and all progress tracking.
        $video = Video::factory()->create([
            'video_path' => 'https://cdn.example.com/lessons/intro.mp4',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('videos.show', $video))
            ->assertOk()
            ->assertSee('<source src="https://cdn.example.com/lessons/intro.mp4">', false)
            ->assertSee('nodownload', false)
            ->assertSee('videoProgress(')
            ->assertDontSee('<iframe', false);
    }

    public function test_an_uploaded_file_still_plays_through_the_private_stream_route(): void
    {
        $video = Video::factory()->create(['video_path' => 'videos/lesson.mp4']);

        // The address is minted per viewer and expires, so assert its shape
        // rather than a fixed URL. VideoStreamSecurityTest covers the rules.
        $html = $this->actingAs(User::factory()->create())
            ->get(route('videos.show', $video))
            ->assertOk()
            ->assertSee('videoProgress(')
            ->assertDontSee('<iframe', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '~<source src="https?://[^"]*/stream/'.$video->slug.'/[a-f0-9]{64}\?expires=\d+&amp;signature=[a-f0-9]+"~',
            $html,
        );
    }

    public function test_an_embedded_player_does_not_pretend_to_track_progress(): void
    {
        // We cannot read a playhead inside somebody else's iframe, so firing
        // position beacons for one would only write zeroes.
        $video = Video::factory()->create([
            'video_path' => 'https://player.vimeo.com/video/76979871',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('videos.show', $video))
            ->assertOk()
            ->assertSee('<iframe src="https://player.vimeo.com/video/76979871"', false)
            ->assertDontSee('videoProgress(');
    }
}

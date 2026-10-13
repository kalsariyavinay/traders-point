<?php

namespace Tests\Unit;

use App\Support\VideoEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VideoEmbedTest extends TestCase
{
    /**
     * Every form an admin can realistically paste, and the frame URL it has to
     * become. The left column is what a browser address bar shows; all of those
     * answer X-Frame-Options and render an empty box if used directly.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function links(): array
    {
        $embed = 'https://www.youtube.com/embed/AkMsMDk_brU';

        return [
            'youtube watch page' => ['https://www.youtube.com/watch?v=AkMsMDk_brU', $embed],
            'youtube without www' => ['https://youtube.com/watch?v=AkMsMDk_brU', $embed],
            'youtube mobile' => ['https://m.youtube.com/watch?v=AkMsMDk_brU', $embed],
            'youtube share link' => ['https://youtu.be/AkMsMDk_brU', $embed],
            'youtube short' => ['https://www.youtube.com/shorts/AkMsMDk_brU', $embed],
            'youtube premiere' => ['https://www.youtube.com/live/AkMsMDk_brU', $embed],

            // The player reads `start`, in seconds, whatever `t` was written as.
            'start offset in seconds' => ['https://youtu.be/AkMsMDk_brU?t=42', $embed.'?start=42'],
            'start offset with suffix' => ['https://www.youtube.com/watch?v=AkMsMDk_brU&t=90s', $embed.'?start=90'],
            'start offset in minutes' => ['https://www.youtube.com/watch?v=AkMsMDk_brU&t=1m30s', $embed.'?start=90'],
            'start offset in hours' => ['https://www.youtube.com/watch?v=AkMsMDk_brU&t=1h0m5s', $embed.'?start=3605'],

            // A lesson inside a playlist keeps its playlist, drops the rest.
            'playlist is carried over' => [
                'https://www.youtube.com/watch?v=AkMsMDk_brU&list=PLabc123&index=2',
                $embed.'?list=PLabc123',
            ],

            'vimeo page' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871'],
            'vimeo unlisted' => [
                'https://vimeo.com/76979871/abc123def4',
                'https://player.vimeo.com/video/76979871?h=abc123def4',
            ],
            'drive share link' => [
                'https://drive.google.com/file/d/1AbCdEfGh/view?usp=sharing',
                'https://drive.google.com/file/d/1AbCdEfGh/preview',
            ],
        ];
    }

    #[DataProvider('links')]
    public function test_a_page_url_becomes_an_embeddable_url(string $pasted, string $expected): void
    {
        $this->assertSame($expected, VideoEmbed::resolve($pasted));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function alreadyPlayable(): array
    {
        return [
            'youtube embed' => ['https://www.youtube.com/embed/AkMsMDk_brU'],
            'youtube embed with params' => ['https://www.youtube.com/embed/AkMsMDk_brU?start=30'],
            'privacy-friendly youtube' => ['https://www.youtube-nocookie.com/embed/AkMsMDk_brU'],
            'vimeo player' => ['https://player.vimeo.com/video/76979871'],
            'a self-hosted player' => ['https://player.example.com/embed/lesson-one'],
            'a direct file' => ['https://cdn.example.com/lessons/intro.mp4'],
        ];
    }

    #[DataProvider('alreadyPlayable')]
    public function test_a_url_that_already_works_is_left_alone(string $url): void
    {
        $this->assertSame($url, VideoEmbed::resolve($url));
        $this->assertFalse(VideoEmbed::wasRewritten($url));
    }

    /**
     * Guessing at a URL we do not understand would break links that work, so
     * anything unrecognised passes through untouched.
     *
     * @return array<string, array{0: string}>
     */
    public static function notRewritable(): array
    {
        return [
            'a stored file' => ['videos/lesson.mp4'],
            'empty' => [''],
            'a playlist rather than a video' => ['https://www.youtube.com/playlist?list=PLabc'],
            'a channel' => ['https://www.youtube.com/@tradersPoint'],
            'a watch url with no id' => ['https://www.youtube.com/watch?feature=share'],
            'an id of the wrong shape' => ['https://www.youtube.com/watch?v=tooshort'],
            'a vimeo channel path' => ['https://vimeo.com/channels/staffpicks'],
            'a drive folder' => ['https://drive.google.com/drive/folders/1AbCdEf'],
        ];
    }

    #[DataProvider('notRewritable')]
    public function test_an_unrecognised_link_is_returned_unchanged(string $url): void
    {
        $this->assertSame($url, VideoEmbed::resolve($url));
    }

    public function test_a_rewritten_link_is_reported_so_the_admin_can_see_it(): void
    {
        $this->assertTrue(VideoEmbed::wasRewritten('https://www.youtube.com/watch?v=AkMsMDk_brU'));
        $this->assertFalse(VideoEmbed::wasRewritten('https://www.youtube.com/embed/AkMsMDk_brU'));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function mediaUrls(): array
    {
        return [
            'mp4' => ['https://cdn.example.com/a.mp4', true],
            'webm' => ['https://cdn.example.com/a.webm', true],
            'mov' => ['https://cdn.example.com/a.mov', true],
            'uppercase extension' => ['https://cdn.example.com/A.MP4', true],
            'with a query string' => ['https://cdn.example.com/a.mp4?token=abc', true],
            'a player page' => ['https://www.youtube.com/watch?v=AkMsMDk_brU', false],
            'no extension' => ['https://cdn.example.com/stream', false],
            'a stored file is not remote' => ['videos/a.mp4', false],
        ];
    }

    #[DataProvider('mediaUrls')]
    public function test_a_direct_video_file_is_told_apart_from_a_player_page(string $url, bool $expected): void
    {
        $this->assertSame($expected, VideoEmbed::isDirectMedia($url));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function placeholders(): array
    {
        return [
            'the seeded demo host' => ['https://player.example.com/embed/lesson', true],
            'example.net' => ['https://cdn.example.net/a.mp4', true],
            'a .test domain' => ['https://videos.test/lesson', true],
            'a .invalid domain' => ['https://host.invalid/lesson', true],
            'a real host that merely contains the word' => ['https://myexample.com/lesson', false],
            'a real host' => ['https://www.youtube.com/watch?v=AkMsMDk_brU', false],
            'a stored file' => ['videos/lesson.mp4', false],
        ];
    }

    #[DataProvider('placeholders')]
    public function test_a_link_that_can_never_resolve_is_recognised(string $url, bool $expected): void
    {
        $this->assertSame($expected, VideoEmbed::isPlaceholder($url));
    }
}

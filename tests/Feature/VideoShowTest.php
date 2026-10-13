<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_free_video_plays_for_a_guest(): void
    {
        $video = Video::factory()->create([
            'video_path' => 'https://player.example.com/embed/free-one',
        ]);

        $this->get(route('videos.show', $video))
            ->assertOk()
            ->assertViewHas('hasAccess', true)
            ->assertSee('Open to everyone')
            ->assertSee('player.example.com/embed/free-one')
            ->assertDontSee('This lesson is paid');
    }

    public function test_a_paid_video_is_locked_for_a_guest(): void
    {
        $video = Video::factory()->paid(999.00)->create([
            'video_path' => 'https://player.example.com/embed/paid-one',
        ]);

        $this->get(route('videos.show', $video))
            ->assertOk()
            ->assertViewHas('hasAccess', false)
            ->assertSee('This lesson is paid')
            ->assertSee('Sign in to buy')
            ->assertSee('999')
            ->assertDontSee('player.example.com/embed/paid-one');
    }

    public function test_a_signed_in_non_buyer_gets_the_razorpay_button(): void
    {
        $video = Video::factory()->paid()->create([
            'video_path' => 'https://player.example.com/embed/paid-two',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('videos.show', $video))
            ->assertOk()
            ->assertSee('Buy now with Razorpay')
            ->assertDontSee('Sign in to buy')
            ->assertDontSee('player.example.com/embed/paid-two');
    }

    public function test_a_buyer_gets_the_player(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->paid()->create([
            'video_path' => 'https://player.example.com/embed/paid-three',
        ]);

        $user->purchasedVideos()->attach($video);

        $this->actingAs($user)
            ->get(route('videos.show', $video))
            ->assertOk()
            ->assertViewHas('hasAccess', true)
            ->assertSee('In your library')
            ->assertSee('player.example.com/embed/paid-three')
            ->assertDontSee('Buy now with Razorpay');
    }

    public function test_an_admin_can_watch_a_paid_video_without_buying(): void
    {
        $video = Video::factory()->paid()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('videos.show', $video))
            ->assertOk()
            ->assertViewHas('hasAccess', true)
            ->assertSee('Admin access');
    }

    public function test_a_draft_is_hidden_from_everyone_but_admins(): void
    {
        $video = Video::factory()->draft()->create();

        $this->get(route('videos.show', $video))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('videos.show', $video))->assertNotFound();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('videos.show', $video))
            ->assertOk()
            ->assertSee('visible to administrators only', false);
    }

    public function test_the_page_title_and_description_describe_the_video(): void
    {
        $video = Video::factory()->create([
            'title' => 'Reading the Option Chain',
            'slug' => 'reading-the-option-chain',
            'description' => 'Open interest, strikes and premium decay.',
        ]);

        $this->get(route('videos.show', $video))
            ->assertOk()
            ->assertSee('<title>Reading the Option Chain · Traders Point Academy</title>', false)
            ->assertSee('content="Open interest, strikes and premium decay."', false);
    }

    public function test_related_videos_come_from_the_same_category_only(): void
    {
        $category = Category::factory()->create();
        $other = Category::factory()->create();

        $video = Video::factory()->for($category)->create();
        Video::factory()->for($category)->create(['title' => 'Same Shelf', 'slug' => 'same-shelf']);
        Video::factory()->for($category)->draft()->create(['title' => 'Draft Sibling', 'slug' => 'draft-sibling']);
        Video::factory()->for($other)->create(['title' => 'Other Shelf', 'slug' => 'other-shelf']);

        $response = $this->get(route('videos.show', $video));

        $response->assertOk()
            ->assertSee('Same Shelf')
            ->assertDontSee('Other Shelf')
            ->assertDontSee('Draft Sibling');

        $this->assertCount(1, $response->viewData('related'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_hero_and_stats_render_for_guests(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(config('academy.hero.headline'))
            ->assertSee(config('academy.stats.0.label'))
            ->assertSee(config('academy.faqs.0.question'))
            ->assertSee(config('academy.testimonials.0.name'));
    }

    public function test_only_published_videos_are_listed(): void
    {
        Video::factory()->create(['title' => 'Live Lesson', 'slug' => 'live-lesson']);
        Video::factory()->draft()->create(['title' => 'Hidden Lesson', 'slug' => 'hidden-lesson']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Live Lesson')
            ->assertDontSee('Hidden Lesson');
    }

    public function test_videos_can_be_filtered_by_category(): void
    {
        $technical = Category::factory()->create(['name' => 'Technical Analysis', 'slug' => 'technical-analysis']);
        $options = Category::factory()->create(['name' => 'Options Trading', 'slug' => 'options-trading']);

        Video::factory()->for($technical)->create(['title' => 'Candlestick Basics', 'slug' => 'candlestick-basics']);
        Video::factory()->for($options)->create(['title' => 'Options Greeks', 'slug' => 'options-greeks']);

        $this->get(route('home', ['category' => 'technical-analysis']))
            ->assertOk()
            ->assertSee('Candlestick Basics')
            ->assertDontSee('Options Greeks');
    }

    public function test_the_free_filter_hides_paid_videos(): void
    {
        Video::factory()->create(['title' => 'Open Lesson', 'slug' => 'open-lesson']);
        Video::factory()->paid()->create(['title' => 'Premium Lesson', 'slug' => 'premium-lesson']);

        $response = $this->get(route('home', ['free' => 1]));

        $response->assertOk()
            ->assertViewHas('freeOnly', true)
            ->assertSee('Open Lesson')
            ->assertDontSee('Premium Lesson');
    }

    public function test_only_categories_with_published_videos_get_a_tab(): void
    {
        $stocked = Category::factory()->create(['name' => 'Stocked', 'slug' => 'stocked']);
        Category::factory()->create(['name' => 'Bare Shelf', 'slug' => 'bare-shelf']);
        Video::factory()->for($stocked)->create();

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Stocked')
            ->assertDontSee('Bare Shelf');
    }

    public function test_the_catalogue_paginates_at_nine(): void
    {
        Video::factory()->count(11)->create();

        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertCount(9, $response->viewData('videos'));
        $this->assertSame(11, $response->viewData('videos')->total());
    }

    public function test_an_impossible_filter_shows_the_empty_state(): void
    {
        Video::factory()->paid()->create();

        $this->get(route('home', ['free' => 1]))
            ->assertOk()
            ->assertSee('Nothing published here yet')
            ->assertSee('Clear filters');
    }

    public function test_a_buyer_sees_their_purchase_marked_as_owned(): void
    {
        $user = User::factory()->create();
        $owned = Video::factory()->paid()->create(['title' => 'Mine', 'slug' => 'mine']);
        Video::factory()->paid()->create(['title' => 'Not Mine', 'slug' => 'not-mine']);

        $user->purchasedVideos()->attach($owned);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk()->assertViewHas('ownedIds', [$owned->id]);
        $this->assertSame(1, substr_count($response->getContent(), 'title="You own this"'));
        $this->assertSame(1, substr_count($response->getContent(), 'title="Locked"'));
    }
}

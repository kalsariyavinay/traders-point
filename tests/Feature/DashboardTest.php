<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_an_empty_dashboard_invites_you_to_browse(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Nothing here yet')
            ->assertSee('Browse courses');
    }

    public function test_it_lists_both_purchases_and_free_videos_watched(): void
    {
        $user = User::factory()->create();

        $bought = Video::factory()->paid()->create(['title' => 'Options Greeks', 'slug' => 'options-greeks']);
        $watched = Video::factory()->create(['title' => 'Market Basics', 'slug' => 'market-basics']);
        Video::factory()->create(['title' => 'Never Opened', 'slug' => 'never-opened']);

        $user->purchasedVideos()->attach($bought);
        $user->recordWatch($watched);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Options Greeks')
            ->assertSee('Market Basics')
            ->assertDontSee('Never Opened');
    }

    public function test_opening_a_free_video_enrols_it(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->create(['title' => 'Market Basics', 'slug' => 'market-basics']);

        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Market Basics');

        $this->actingAs($user)->get(route('videos.show', $video))->assertOk();

        $this->assertDatabaseHas('video_views', [
            'user_id' => $user->id,
            'video_id' => $video->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Market Basics');
    }

    public function test_opening_a_locked_video_does_not_enrol_it(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->paid()->create(['title' => 'Locked Lesson', 'slug' => 'locked-lesson']);

        $this->actingAs($user)->get(route('videos.show', $video))->assertOk();

        $this->assertDatabaseEmpty('video_views');
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Locked Lesson');
    }

    public function test_watching_twice_keeps_one_row(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->create();

        $this->actingAs($user)->get(route('videos.show', $video));
        $this->actingAs($user)->get(route('videos.show', $video));

        $this->assertDatabaseCount('video_views', 1);
    }

    public function test_a_watch_is_not_a_purchase(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->create();

        $this->actingAs($user)->get(route('videos.show', $video));

        $this->assertDatabaseEmpty('video_user');
        $this->assertFalse($user->fresh()->hasPurchased($video));
    }

    public function test_the_bought_filter_hides_free_videos(): void
    {
        $user = User::factory()->create();
        $bought = Video::factory()->paid()->create(['title' => 'Options Greeks', 'slug' => 'options-greeks']);
        $watched = Video::factory()->create(['title' => 'Market Basics', 'slug' => 'market-basics']);

        $user->purchasedVideos()->attach($bought);
        $user->recordWatch($watched);

        $this->actingAs($user)
            ->get(route('dashboard', ['filter' => 'purchased']))
            ->assertOk()
            ->assertViewHas('filter', 'purchased')
            ->assertSee('Options Greeks')
            ->assertDontSee('Market Basics');
    }

    public function test_the_free_filter_hides_purchases(): void
    {
        $user = User::factory()->create();
        $bought = Video::factory()->paid()->create(['title' => 'Options Greeks', 'slug' => 'options-greeks']);
        $watched = Video::factory()->create(['title' => 'Market Basics', 'slug' => 'market-basics']);

        $user->purchasedVideos()->attach($bought);
        $user->recordWatch($watched);

        $this->actingAs($user)
            ->get(route('dashboard', ['filter' => 'free']))
            ->assertOk()
            ->assertSee('Market Basics')
            ->assertDontSee('Options Greeks');
    }

    public function test_an_unknown_filter_falls_back_to_all(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard', ['filter' => 'bogus']))
            ->assertOk()
            ->assertViewHas('filter', 'all');
    }

    public function test_the_figures_separate_purchases_from_free_views(): void
    {
        $user = User::factory()->create();
        $bought = Video::factory()->paid()->create();
        $user->purchasedVideos()->attach($bought);
        $user->recordWatch(Video::factory()->create());
        $user->recordWatch(Video::factory()->create());

        Order::factory()->successful()->for($user)->for($bought)->create(['amount' => 1499.00]);
        Order::factory()->failed()->for($user)->for($bought)->create(['amount' => 9999.00]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('purchasedCount', 1)
            ->assertViewHas('watchedCount', 2)
            ->assertViewHas('spent', 1499.0)
            ->assertSee('₹1,499');
    }

    public function test_drafts_never_show_even_if_they_were_watched(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->create(['title' => 'Later Drafted', 'slug' => 'later-drafted']);

        $user->recordWatch($video);
        $video->update(['status' => \App\Enums\VideoStatus::Draft]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Later Drafted');
    }

    public function test_the_dashboard_paginates(): void
    {
        $user = User::factory()->create();
        $user->purchasedVideos()->attach(Video::factory()->paid()->count(11)->create());

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $this->assertCount(9, $response->viewData('videos'));
        $this->assertSame(11, $response->viewData('videos')->total());
    }

    public function test_every_enrolled_video_offers_a_watch_link(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->paid()->create();
        $user->purchasedVideos()->attach($video);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('videos.show', $video))
            ->assertSee('Watch');
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_regular_users_cannot_manage_categories(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.categories.store'), ['name' => 'Hacked'])->assertForbidden();

        $this->assertDatabaseEmpty('categories');
    }

    public function test_index_can_be_filtered_by_search(): void
    {
        Category::factory()->create(['name' => 'Options Trading', 'slug' => 'options-trading']);
        Category::factory()->create(['name' => 'Technical Analysis', 'slug' => 'technical-analysis']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.categories.index', ['search' => 'Options']));

        $response->assertOk()
            ->assertSee('Options Trading')
            ->assertDontSee('Technical Analysis');
    }

    public function test_a_category_is_created_with_a_generated_slug(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Basics of Stock Market']);

        $response->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categories', [
            'name' => 'Basics of Stock Market',
            'slug' => 'basics-of-stock-market',
        ]);
    }

    public function test_duplicate_category_names_are_rejected(): void
    {
        Category::factory()->create(['name' => 'Options Trading', 'slug' => 'options-trading']);

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Options Trading'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Category::count());
    }

    public function test_colliding_slugs_are_suffixed(): void
    {
        Category::factory()->create(['name' => 'Options Trading', 'slug' => 'options-trading']);

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Options  trading!'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', ['slug' => 'options-trading-2']);
    }

    public function test_renaming_a_category_regenerates_its_slug(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $category), ['name' => 'New Name'])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'New Name',
            'slug' => 'new-name',
        ]);
    }

    public function test_a_category_keeps_its_own_name_on_update(): void
    {
        $category = Category::factory()->create(['name' => 'Keep Me', 'slug' => 'keep-me']);

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $category), ['name' => 'Keep Me'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'slug' => 'keep-me']);
    }

    public function test_a_category_with_videos_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Video::factory()->for($category)->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}

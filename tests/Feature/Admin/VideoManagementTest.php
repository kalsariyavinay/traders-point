<?php

namespace Tests\Feature\Admin;

use App\Enums\VideoStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VideoManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->admin = User::factory()->admin()->create();
        $this->category = Category::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Reading Candlestick Charts',
            'category_id' => $this->category->id,
            'description' => 'An intro to candlesticks.',
            'status' => VideoStatus::Published->value,
            'is_free' => '1',
            'video_source' => 'upload',
            'video_file' => UploadedFile::fake()->create('lesson.mp4', 512, 'video/mp4'),
            ...$overrides,
        ];
    }

    public function test_regular_users_cannot_reach_video_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.videos.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.videos.create'))->assertForbidden();
    }

    public function test_index_search_matches_title_and_category(): void
    {
        $other = Category::factory()->create(['name' => 'Derivatives', 'slug' => 'derivatives']);
        Video::factory()->for($this->category)->create(['title' => 'Candlestick Basics', 'slug' => 'candlestick-basics']);
        Video::factory()->for($other)->create(['title' => 'Futures Pricing', 'slug' => 'futures-pricing']);

        $this->actingAs($this->admin)
            ->get(route('admin.videos.index', ['search' => 'Candlestick']))
            ->assertOk()
            ->assertSee('Candlestick Basics')
            ->assertDontSee('Futures Pricing');

        $this->actingAs($this->admin)
            ->get(route('admin.videos.index', ['search' => 'Derivatives']))
            ->assertOk()
            ->assertSee('Futures Pricing')
            ->assertDontSee('Candlestick Basics');
    }

    public function test_a_free_video_is_stored_on_the_private_disk(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.videos.store'), $this->payload())
            ->assertRedirect(route('admin.videos.index'))
            ->assertSessionHasNoErrors();

        $video = Video::sole();

        $this->assertSame('reading-candlestick-charts', $video->slug);
        $this->assertTrue($video->is_free);
        $this->assertSame('0.00', $video->price);
        $this->assertSame(VideoStatus::Published, $video->status);
        $this->assertFalse($video->isExternalVideo());

        Storage::disk('local')->assertExists($video->video_path);
        $this->assertStringStartsWith('videos/', $video->video_path);
    }

    public function test_a_paid_video_keeps_its_price(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.videos.store'), $this->payload([
                'is_free' => '0',
                'price' => '1499.50',
            ]))
            ->assertSessionHasNoErrors();

        $video = Video::sole();

        $this->assertFalse($video->is_free);
        $this->assertSame('1499.50', $video->price);
    }

    public function test_marking_a_video_free_forces_the_price_to_zero(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.videos.store'), $this->payload([
                'is_free' => '1',
                'price' => '999',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('0.00', Video::sole()->price);
    }

    public function test_a_paid_video_requires_a_price(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.videos.store'), $this->payload(['is_free' => '0', 'price' => null]))
            ->assertSessionHasErrors('price');

        $this->assertDatabaseEmpty('videos');
    }

    public function test_an_external_link_can_be_used_instead_of_an_upload(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.videos.store'), $this->payload([
                'video_source' => 'link',
                'video_file' => null,
                'video_url' => 'https://player.example.com/embed/abc123',
            ]))
            ->assertSessionHasNoErrors();

        $video = Video::sole();

        $this->assertTrue($video->isExternalVideo());
        $this->assertSame('https://player.example.com/embed/abc123', $video->video_path);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_an_upload_requires_a_file_and_a_link_requires_a_url(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.videos.store'), $this->payload(['video_file' => null]))
            ->assertSessionHasErrors('video_file');

        $this->actingAs($this->admin)
            ->post(route('admin.videos.store'), $this->payload([
                'video_source' => 'link',
                'video_file' => null,
            ]))
            ->assertSessionHasErrors('video_url');

        $this->assertDatabaseEmpty('videos');
    }

    public function test_non_video_uploads_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.videos.store'), $this->payload([
                'video_file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('video_file');
    }

    public function test_a_thumbnail_is_stored_on_the_public_disk(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.videos.store'), $this->payload([
                'thumbnail' => UploadedFile::fake()->image('cover.jpg', 640, 360),
            ]))
            ->assertSessionHasNoErrors();

        $video = Video::sole();

        Storage::disk('public')->assertExists($video->thumbnail);
        $this->assertStringContainsString('/storage/'.$video->thumbnail, $video->thumbnailUrl());
    }

    public function test_updating_with_an_empty_file_input_keeps_the_stored_video(): void
    {
        $this->actingAs($this->admin)->post(route('admin.videos.store'), $this->payload());
        $video = Video::sole();
        $originalPath = $video->video_path;

        $this->actingAs($this->admin)
            ->put(route('admin.videos.update', $video), [
                'title' => $video->title,
                'category_id' => $this->category->id,
                'description' => 'Updated copy.',
                'status' => VideoStatus::Draft->value,
                'is_free' => '1',
                'video_source' => 'upload',
            ])
            ->assertRedirect(route('admin.videos.index'))
            ->assertSessionHasNoErrors();

        $video->refresh();

        $this->assertSame($originalPath, $video->video_path);
        $this->assertSame('Updated copy.', $video->description);
        $this->assertSame(VideoStatus::Draft, $video->status);
        Storage::disk('local')->assertExists($originalPath);
    }

    public function test_replacing_the_file_removes_the_previous_one(): void
    {
        $this->actingAs($this->admin)->post(route('admin.videos.store'), $this->payload());
        $video = Video::sole();
        $originalPath = $video->video_path;

        $this->actingAs($this->admin)
            ->put(route('admin.videos.update', $video), [
                'title' => $video->title,
                'category_id' => $this->category->id,
                'status' => VideoStatus::Published->value,
                'is_free' => '1',
                'video_source' => 'upload',
                'video_file' => UploadedFile::fake()->create('replacement.mp4', 256, 'video/mp4'),
            ])
            ->assertSessionHasNoErrors();

        $video->refresh();

        $this->assertNotSame($originalPath, $video->video_path);
        Storage::disk('local')->assertMissing($originalPath);
        Storage::disk('local')->assertExists($video->video_path);
    }

    public function test_switching_to_a_link_removes_the_uploaded_file(): void
    {
        $this->actingAs($this->admin)->post(route('admin.videos.store'), $this->payload());
        $video = Video::sole();
        $originalPath = $video->video_path;

        $this->actingAs($this->admin)
            ->put(route('admin.videos.update', $video), [
                'title' => $video->title,
                'category_id' => $this->category->id,
                'status' => VideoStatus::Published->value,
                'is_free' => '1',
                'video_source' => 'link',
                'video_url' => 'https://player.example.com/embed/xyz',
            ])
            ->assertSessionHasNoErrors();

        $video->refresh();

        $this->assertTrue($video->isExternalVideo());
        Storage::disk('local')->assertMissing($originalPath);
    }

    public function test_switching_a_linked_video_to_upload_requires_a_file(): void
    {
        $video = Video::factory()->create([
            'video_path' => 'https://player.example.com/embed/xyz',
            'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.videos.update', $video), [
                'title' => $video->title,
                'category_id' => $this->category->id,
                'status' => VideoStatus::Published->value,
                'is_free' => '1',
                'video_source' => 'upload',
            ])
            ->assertSessionHasErrors('video_file');
    }

    public function test_deleting_a_video_removes_its_files(): void
    {
        $this->actingAs($this->admin)->post(route('admin.videos.store'), $this->payload([
            'thumbnail' => UploadedFile::fake()->image('cover.jpg'),
        ]));

        $video = Video::sole();
        $videoPath = $video->video_path;
        $thumbnailPath = $video->thumbnail;

        $this->actingAs($this->admin)
            ->delete(route('admin.videos.destroy', $video))
            ->assertRedirect(route('admin.videos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseEmpty('videos');
        Storage::disk('local')->assertMissing($videoPath);
        Storage::disk('public')->assertMissing($thumbnailPath);
    }

    public function test_a_video_with_paid_orders_cannot_be_deleted(): void
    {
        $video = Video::factory()->paid()->for($this->category)->create();
        Order::factory()->successful()->for($video)->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.videos.destroy', $video))
            ->assertRedirect(route('admin.videos.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('videos', ['id' => $video->id]);
        $this->assertDatabaseHas('orders', ['video_id' => $video->id]);
    }

    /**
     * The edit form for a free video used to be unsaveable: price 0.00 against
     * a client-side min="1" on a field x-show had hidden. The server was never
     * the obstacle, and must stay that way whether the browser sends the
     * price, sends 0.00, or (now that the hidden branch is disabled) omits it.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function freePricePayloads(): array
    {
        return [
            'price omitted, as a disabled input does' => [[]],
            'price sent as the stored zero' => [['price' => '0.00']],
            'price sent as a bare zero' => [['price' => '0']],
        ];
    }

    #[DataProvider('freePricePayloads')]
    public function test_a_free_video_can_be_saved_however_the_browser_sends_its_price(array $price): void
    {
        $video = Video::factory()->create([
            'category_id' => $this->category->id,
            'is_free' => true,
            'price' => 0,
            'video_path' => 'https://www.youtube.com/watch?v=AkMsMDk_brU',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.videos.update', $video), [
                'title' => 'How the Stock Market Works',
                'category_id' => $this->category->id,
                'description' => 'Edited by an admin.',
                'status' => VideoStatus::Published->value,
                'is_free' => '1',
                'video_source' => 'link',
                'video_url' => 'https://www.youtube.com/watch?v=AkMsMDk_brU',
                ...$price,
            ])
            ->assertRedirect(route('admin.videos.index'))
            ->assertSessionHasNoErrors();

        $video->refresh();

        $this->assertSame('Edited by an admin.', $video->description);
        $this->assertSame('0.00', $video->price);
        $this->assertTrue($video->is_free);
    }

    public function test_a_link_too_long_for_the_column_is_reported_as_a_field_error(): void
    {
        // video_path is varchar(255). Accepting more only to have the insert
        // throw turns a fixable mistake into "something went wrong".
        $this->actingAs($this->admin)
            ->post(route('admin.videos.store'), $this->payload([
                'video_source' => 'link',
                'video_file' => null,
                'video_url' => 'https://player.example.com/embed/'.str_repeat('a', 240),
            ]))
            ->assertSessionHasErrors('video_url');

        $this->assertDatabaseEmpty('videos');
    }

    public function test_a_pasted_page_url_is_stored_as_pasted_and_normalised_when_played(): void
    {
        // Storing the original keeps the "open at the source" fallback honest
        // and the admin's own link recognisable; the rewrite happens at render.
        $this->actingAs($this->admin)
            ->post(route('admin.videos.store'), $this->payload([
                'video_source' => 'link',
                'video_file' => null,
                'video_url' => 'https://youtu.be/AkMsMDk_brU',
            ]))
            ->assertSessionHasNoErrors();

        $video = Video::sole();

        $this->assertSame('https://youtu.be/AkMsMDk_brU', $video->video_path);
        $this->assertSame('https://www.youtube.com/embed/AkMsMDk_brU', $video->embedUrl());
        $this->assertTrue($video->isEmbeddedVideo());
    }

    public function test_the_video_list_flags_a_link_that_can_never_play(): void
    {
        Video::factory()->create([
            'title' => 'Seeded Lesson',
            'category_id' => $this->category->id,
            'video_path' => 'https://player.example.com/embed/seeded-lesson',
        ]);
        Video::factory()->create([
            'title' => 'Real Lesson',
            'category_id' => $this->category->id,
            'video_path' => 'https://www.youtube.com/watch?v=AkMsMDk_brU',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.videos.index'))
            ->assertOk()
            ->assertSee('Placeholder link — this lesson cannot play')
            ->assertSeeInOrder(['Seeded Lesson', 'Placeholder link']);
    }
}

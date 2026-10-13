<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Video;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminUxTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /** @return array<string, array{0: string}> */
    public static function forms(): array
    {
        return [
            'category create' => ['admin.categories.create'],
            'coupon create' => ['admin.coupons.create'],
            'video create' => ['admin.videos.create'],
        ];
    }

    // ---------------------------------------------------------------- loading

    #[DataProvider('forms')]
    public function test_every_form_guards_against_a_double_submit(string $route): void
    {
        Category::factory()->create(); // the video form needs one to render

        $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/x-data="admin(Form|Upload)/', $html);
        $this->assertStringContainsString(':disabled="submitting"', $html);
    }

    #[DataProvider('forms')]
    public function test_every_submit_button_shows_it_is_working(string $route): void
    {
        Category::factory()->create();

        $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

        $this->assertStringContainsString('animate-spin', $html);
        $this->assertStringContainsString('Saving…', $html);
    }

    public function test_the_video_form_reports_upload_progress(): void
    {
        Category::factory()->create();

        $html = $this->actingAs($this->admin)->get(route('admin.videos.create'))->assertOk()->getContent();

        $this->assertStringContainsString('x-data="adminUpload', $html);
        $this->assertStringContainsString('role="progressbar"', $html);
        $this->assertStringContainsString("x-text=\"progress + '%'\"", $html);
        $this->assertStringContainsString('Do not close this tab', $html);
        $this->assertStringContainsString('can take a few minutes', $html);
    }

    public function test_the_upload_limits_reach_the_browser(): void
    {
        Category::factory()->create();

        $html = $this->actingAs($this->admin)->get(route('admin.videos.create'))->assertOk()->getContent();

        // 120 MB and 2 MB in bytes, so an oversize pick is caught before upload.
        $this->assertStringContainsString('maxVideoBytes: 125829120', $html);
        $this->assertStringContainsString('maxThumbnailBytes: 2097152', $html);
    }

    // ------------------------------------------------------------- validation

    public function test_an_invalid_category_comes_back_with_a_summary_and_field_error(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), ['name' => '']);

        $response->assertRedirect(route('admin.categories.create'))->assertSessionHasErrors('name');

        $html = $this->actingAs($this->admin)->get(route('admin.categories.create'))->getContent();

        $this->assertStringContainsString('Please fix the field below.', $html);
        $this->assertStringContainsString('text-red-600', $html);
    }

    public function test_the_summary_counts_multiple_bad_fields(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.coupons.create'))
            ->post(route('admin.coupons.store'), ['code' => '', 'discount_percent' => '999'])
            ->assertSessionHasErrors(['code', 'discount_percent']);

        $html = $this->actingAs($this->admin)->get(route('admin.coupons.create'))->getContent();

        $this->assertStringContainsString('Please fix the 2 fields below.', $html);
    }

    public function test_an_invalid_xhr_upload_returns_field_errors_as_json(): void
    {
        Storage::fake('local');
        $category = Category::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.videos.store'), [
                'title' => '',
                'category_id' => $category->id,
                'status' => 'published',
                'is_free' => '0',
                'video_source' => 'upload',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'price', 'video_file']);

        $this->assertDatabaseEmpty('videos');
    }

    public function test_a_successful_xhr_upload_returns_a_redirect_and_flashes_the_toast(): void
    {
        Storage::fake('local');
        $category = Category::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.videos.store'), [
                'title' => 'Reading the Tape',
                'category_id' => $category->id,
                'status' => 'published',
                'is_free' => '1',
                'video_source' => 'link',
                'video_url' => 'https://player.example.com/embed/tape',
            ])
            ->assertOk()
            ->assertJsonPath('redirect', route('admin.videos.index'))
            // The browser navigates itself, so the flash must survive for the
            // page it lands on rather than being eaten by a followed redirect.
            ->assertSessionHas('success', '"Reading the Tape" was created.');
    }

    // ---------------------------------------------------------- confirmations

    public function test_destructive_actions_use_a_real_dialog_not_window_confirm(): void
    {
        Video::factory()->create();
        Category::factory()->create();
        Coupon::factory()->create();

        foreach (['admin.videos.index', 'admin.categories.index', 'admin.coupons.index'] as $route) {
            $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

            $this->assertStringNotContainsString('return confirm(', $html, "{$route} still uses window.confirm");
            $this->assertStringContainsString('x-teleport="body"', $html, "{$route} has no dialog");
            $this->assertStringContainsString('aria-modal="true"', $html);
        }
    }

    public function test_the_dialog_posts_a_real_csrf_protected_form(): void
    {
        $video = Video::factory()->create();

        $html = $this->actingAs($this->admin)->get(route('admin.videos.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.videos.destroy', $video), $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    public function test_pausing_a_coupon_is_confirmed_too(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'DIWALI25']);

        $this->actingAs($this->admin)
            ->get(route('admin.coupons.index'))
            ->assertOk()
            ->assertSee('Pause DIWALI25?')
            ->assertSee('name="_method" value="PATCH"', false);
    }

    // ------------------------------------------------------- failure handling

    private function routeThatThrows(\Throwable $e): string
    {
        Route::middleware(['web', 'auth', 'admin'])
            ->post('/admin/__explode', fn () => throw $e)
            ->name('admin.explode');

        return '/admin/__explode';
    }

    public function test_an_unexpected_error_returns_a_toast_instead_of_a_500_page(): void
    {
        $url = $this->routeThatThrows(new RuntimeException('database on fire'));

        $this->actingAs($this->admin)
            ->from(route('admin.categories.create'))
            ->post($url, ['name' => 'Half typed'])
            ->assertRedirect(route('admin.categories.create'))
            ->assertSessionHas('error')
            // Whatever they had typed comes back with them.
            ->assertSessionHasInput('name', 'Half typed');
    }

    public function test_an_unexpected_error_during_an_xhr_upload_returns_json(): void
    {
        $url = $this->routeThatThrows(new RuntimeException('disk full'));

        $this->actingAs($this->admin)
            ->postJson($url, [])
            ->assertStatus(500)
            ->assertJsonStructure(['message']);
    }

    public function test_an_expired_session_is_explained_rather_than_shown_as_page_expired(): void
    {
        $url = $this->routeThatThrows(new TokenMismatchException);

        $this->actingAs($this->admin)
            ->from(route('admin.videos.create'))
            ->post($url, [])
            ->assertRedirect(route('admin.videos.create'))
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'session expired'));
    }

    public function test_a_404_is_still_a_404(): void
    {
        $url = $this->routeThatThrows(new NotFoundHttpException);

        $this->actingAs($this->admin)->post($url, [])->assertNotFound();
    }

    public function test_an_authorisation_failure_is_still_a_403(): void
    {
        $url = $this->routeThatThrows(new AuthorizationException);

        $this->actingAs($this->admin)->post($url, [])->assertForbidden();
    }

    public function test_validation_still_behaves_normally_under_the_handler(): void
    {
        // The handler must not turn a 422 into a generic "something went wrong".
        $this->actingAs($this->admin)
            ->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), ['name' => ''])
            ->assertSessionHasErrors('name')
            ->assertSessionMissing('error');
    }

    public function test_reads_are_left_alone_by_the_handler(): void
    {
        Route::middleware(['web', 'auth', 'admin'])
            ->get('/admin/__read-explode', fn () => throw new RuntimeException('boom'));

        // A GET has no input worth rescuing; it should surface, not redirect.
        $this->expectException(RuntimeException::class);

        $this->withoutExceptionHandling()
            ->actingAs($this->admin)
            ->get('/admin/__read-explode');
    }

    // ------------------------------------------------- hidden-field blocking

    /**
     * The defect this guards against: a free video stores price 0.00, the
     * price input carried min="1", and x-show had hidden it -- so the browser
     * cancelled the submit and could not focus the field to say why. Save did
     * nothing at all, with no message anywhere, for every free video.
     *
     * A hidden branch must therefore be disabled, not merely hidden: a
     * disabled control is excluded from constraint validation entirely.
     */
    public function test_a_hidden_branch_of_the_video_form_cannot_block_the_submit(): void
    {
        Category::factory()->create();
        $free = Video::factory()->create(['is_free' => true, 'price' => 0]);

        foreach ([route('admin.videos.create'), route('admin.videos.edit', $free)] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('x-bind:disabled="isFree"', $html, "{$url}: price can still block");
            $this->assertStringContainsString("x-bind:disabled=\"source !== 'link'\"", $html, "{$url}: video_url can still block");
            $this->assertStringContainsString("x-bind:disabled=\"source !== 'upload'\"", $html, "{$url}: video_file can still ride along");
        }
    }

    /**
     * And the net under that: `invalid` fires even for a control the browser
     * refuses to focus, so a blocked submit can always be explained.
     */
    #[DataProvider('forms')]
    public function test_every_form_can_explain_a_block_it_cannot_show(string $route): void
    {
        Category::factory()->create();

        $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

        $this->assertStringContainsString('x-on:invalid.capture="reportBlocked($event)"', $html);
    }

    public function test_an_expired_session_can_be_renewed_without_losing_the_form(): void
    {
        Category::factory()->create();

        // "Please try again" is a dead end while the _token in the DOM is the
        // expired one: every retry 419s identically.
        $html = $this->actingAs($this->admin)->get(route('admin.videos.create'))->assertOk()->getContent();
        $this->assertStringContainsString('tokenUrl', $html);

        $this->actingAs($this->admin)
            ->getJson(route('csrf-token'))
            ->assertOk()
            ->assertJsonStructure(['token']);
    }
}

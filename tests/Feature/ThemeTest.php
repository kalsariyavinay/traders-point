<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The theme itself lives in the browser: a class on <html>, a localStorage key,
 * and CSS variables. PHPUnit cannot read localStorage, so what is asserted here
 * is the server-rendered contract that makes it work — the pre-paint bootstrap
 * script, the toggle on every surface, and the dark variants the admin needs.
 */
class ThemeTest extends TestCase
{
    use RefreshDatabase;

    private function public_(): string
    {
        return $this->get(route('home'))->assertOk()->getContent();
    }

    private function admin(): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))->assertOk()->getContent();
    }

    private function guest_(): string
    {
        return $this->get(route('login'))->assertOk()->getContent();
    }

    public function test_tailwind_is_configured_for_class_based_dark_mode(): void
    {
        $config = file_get_contents(base_path('tailwind.config.js'));

        $this->assertStringContainsString("darkMode: 'class'", $config);
    }

    /** @return array<string, array{0: string}> */
    public static function surfaces(): array
    {
        return [
            'public' => ['public_'],
            'admin' => ['admin'],
            'guest' => ['guest_'],
        ];
    }

    #[DataProvider('surfaces')]
    public function test_every_layout_ships_the_theme_bootstrap(string $surface): void
    {
        $html = $this->{$surface}();

        $this->assertStringContainsString("localStorage.getItem('theme')", $html);
        $this->assertStringContainsString("classList.toggle('dark'", $html);
    }

    /**
     * A stylesheet loaded before the script would paint the wrong theme first.
     *
     */
    #[DataProvider('surfaces')]
    public function test_the_bootstrap_runs_before_the_stylesheet(string $surface): void
    {
        $html = $this->{$surface}();

        $script = strpos($html, "localStorage.getItem('theme')");

        // Vite hashes the filename, so match the tag rather than "app.css".
        $this->assertNotFalse($script);
        $this->assertMatchesRegularExpression('~rel="stylesheet"~', $html);

        $firstStylesheet = strpos($html, 'rel="stylesheet"');
        $this->assertLessThan($firstStylesheet, $script, 'The theme script must come before every stylesheet.');
    }

    #[DataProvider('surfaces')]
    public function test_every_layout_offers_a_toggle(string $surface): void
    {
        $html = $this->{$surface}();

        $this->assertStringContainsString('$store.theme.toggle()', $html);
        $this->assertStringContainsString('Switch to light mode', $html);
        $this->assertStringContainsString('Switch to dark mode', $html);
    }

    public function test_the_toggle_is_reachable_without_a_mouse(): void
    {
        $html = $this->public_();

        // A real <button>, not a div, and labelled for screen readers.
        $this->assertMatchesRegularExpression('/<button[^>]+type="button"[^>]*>/s', $html);
        $this->assertStringContainsString('aria-label="Switch theme"', $html);
    }

    public function test_the_public_palette_is_driven_by_tokens_not_hardcoded_hex(): void
    {
        $category = Category::factory()->create();
        Video::factory()->for($category)->create();

        $html = $this->public_();

        // Tokens flip with the theme; a baked-in hex would not.
        $this->assertStringContainsString('bg-ink', $html);
        $this->assertStringContainsString('text-paper', $html);
        $this->assertStringNotContainsString('#070B18', $html);
        $this->assertStringNotContainsString('#EAF0FF', $html);
    }

    public function test_the_hero_chart_follows_the_theme(): void
    {
        $html = $this->public_();

        $this->assertStringContainsString('stroke-ink-line', $html);
        $this->assertStringContainsString('fill-muted', $html);
        $this->assertStringContainsString('stroke-gold', $html);
        $this->assertStringNotContainsString('stroke="#1B2540"', $html);
    }

    public function test_the_admin_chrome_has_dark_variants(): void
    {
        $html = $this->admin();

        foreach (['dark:bg-slate-950', 'dark:bg-slate-900', 'dark:text-slate-100', 'dark:border-slate-800'] as $class) {
            $this->assertStringContainsString($class, $html, "Admin layout is missing {$class}.");
        }
    }

    public function test_admin_tables_and_cards_have_dark_variants(): void
    {
        $admin = User::factory()->admin()->create();
        Video::factory()->create();

        $html = $this->actingAs($admin)->get(route('admin.videos.index'))->assertOk()->getContent();

        $this->assertStringContainsString('dark:divide-slate-800', $html);
        $this->assertStringContainsString('dark:bg-slate-900', $html);
        $this->assertStringContainsString('dark:text-slate-400', $html);
    }

    public function test_breeze_form_controls_have_dark_variants(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.categories.create'))->assertOk()->getContent();

        $this->assertStringContainsString('dark:bg-slate-800', $html);   // text input
        $this->assertStringContainsString('dark:text-slate-300', $html); // label
        $this->assertStringContainsString('dark:bg-slate-100', $html);   // primary button inverts
    }

    public function test_the_admin_paginator_has_dark_variants(): void
    {
        $admin = User::factory()->admin()->create();
        Video::factory()->count(12)->create();

        $html = $this->actingAs($admin)->get(route('admin.videos.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Next', $html);
        $this->assertStringContainsString('dark:bg-slate-900', $html);
        $this->assertStringContainsString('dark:border-slate-700', $html);
    }

    public function test_the_public_paginator_uses_themed_tokens(): void
    {
        Video::factory()->count(12)->create();

        $html = $this->public_();

        $this->assertStringContainsString('Next', $html);
        $this->assertStringContainsString('border-ink-line', $html);
    }

    public function test_the_learner_dashboard_renders_on_the_themed_layout(): void
    {
        $user = User::factory()->create();
        $user->purchasedVideos()->attach(Video::factory()->paid()->create());

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString("localStorage.getItem('theme')", $html);
        $this->assertStringContainsString('$store.theme.toggle()', $html);
    }

    public function test_no_admin_view_is_left_light_only(): void
    {
        $light = [];

        foreach (glob(resource_path('views/admin/**/*.blade.php')) + glob(resource_path('views/admin/*.blade.php')) as $file) {
            $contents = file_get_contents($file);

            // A view that styles anything in gray must say what it does in dark.
            if (preg_match('/\b(bg-white|bg-gray-\d{2,3}|text-gray-\d{2,3})\b/', $contents)
                && ! str_contains($contents, 'dark:')) {
                $light[] = str_replace(resource_path('views/'), '', $file);
            }
        }

        $this->assertSame([], $light, 'These admin views have no dark variants: '.implode(', ', $light));
    }
}

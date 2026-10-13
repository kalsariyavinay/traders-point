<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Category, 2: \Illuminate\Support\Collection<int, Video>} */
    private function categoryWith(int $lessons = 2): array
    {
        $user = User::factory()->create(['name' => 'Asha Patel']);
        $category = Category::factory()->create(['name' => 'Technical Analysis', 'slug' => 'technical-analysis']);
        $videos = Video::factory()->count($lessons)->for($category)->create(['duration_seconds' => 100]);

        return [$user, $category, $videos];
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        [, $category] = $this->categoryWith();

        $this->get(route('certificates.download', $category))->assertRedirect(route('login'));
    }

    public function test_an_unfinished_category_has_no_certificate(): void
    {
        [$user, $category, $videos] = $this->categoryWith();

        $user->recordProgress($videos[0], 100);   // one of two

        $this->actingAs($user)
            ->get(route('certificates.download', $category))
            ->assertForbidden();
    }

    public function test_a_category_with_no_published_lessons_is_a_404(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Video::factory()->for($category)->draft()->create();

        $this->actingAs($user)
            ->get(route('certificates.download', $category))
            ->assertNotFound();
    }

    public function test_finishing_the_category_yields_a_pdf(): void
    {
        [$user, $category, $videos] = $this->categoryWith();

        foreach ($videos as $video) {
            $user->fresh()->recordProgress($video, 100);
        }

        $response = $this->actingAs($user)->get(route('certificates.download', $category));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('traders-point-academy-technical-analysis-certificate.pdf');

        // A real PDF, not an error page rendered as one.
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_the_certificate_carries_the_learner_and_course_details(): void
    {
        [$user, $category, $videos] = $this->categoryWith();

        foreach ($videos as $video) {
            $user->fresh()->recordProgress($video, 100);
        }

        // Render the same view the PDF is built from, so the text is readable.
        $html = view('certificates.category', [
            'brand' => config('academy.brand'),
            'name' => $user->name,
            'category' => $category->name,
            'lessons' => 2,
            'completedAt' => now(),
            'reference' => 'TPA-01-00001-ABCD1234',
            'verifyUrl' => route('certificates.verify', 'TPA-01-00001-ABCD1234'),
        ])->render();

        $this->assertStringContainsString('Asha Patel', $html);
        $this->assertStringContainsString('Technical Analysis', $html);
        $this->assertStringContainsString('Certificate of Completion', $html);
        $this->assertStringContainsString(now()->format('d F Y'), $html);
        $this->assertStringContainsString('TPA-01-00001-ABCD1234', $html);
        $this->assertStringContainsString('or financial advice', $html);
    }

    public function test_a_draft_lesson_does_not_block_the_certificate(): void
    {
        [$user, $category, $videos] = $this->categoryWith();
        Video::factory()->for($category)->draft()->create(['duration_seconds' => 100]);

        foreach ($videos as $video) {
            $user->fresh()->recordProgress($video, 100);
        }

        $this->actingAs($user)->get(route('certificates.download', $category))->assertOk();
    }

    public function test_an_issued_certificate_is_not_revoked_by_a_new_lesson(): void
    {
        [$user, $category, $videos] = $this->categoryWith();

        foreach ($videos as $video) {
            $user->fresh()->recordProgress($video, 100);
        }

        $this->actingAs($user)->get(route('certificates.download', $category))->assertOk();

        // Growing the syllabus must not retract an award already made, or every
        // printed certificate and profile link would quietly stop verifying.
        Video::factory()->for($category)->create(['duration_seconds' => 100]);

        $this->actingAs($user)->get(route('certificates.download', $category))->assertOk();
        $this->assertFalse($user->fresh()->hasCompletedCategory($category));
    }

    public function test_the_dashboard_tracks_progress_towards_each_certificate(): void
    {
        [$user, $category, $videos] = $this->categoryWith();

        $user->recordProgress($videos[0], 100);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Certificates')
            ->assertSee('1 / 2 lessons completed')
            ->assertSee('1 to go')
            ->assertDontSee('Download certificate');

        $this->assertFalse($response->viewData('certificates')->first()['earned']);
    }

    public function test_the_dashboard_offers_the_download_once_earned(): void
    {
        [$user, $category, $videos] = $this->categoryWith();

        foreach ($videos as $video) {
            $user->fresh()->recordProgress($video, 100);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Download certificate')
            ->assertSee(route('certificates.download', $category));
    }

    public function test_untouched_categories_are_left_off_the_dashboard(): void
    {
        [$user] = $this->categoryWith();
        Category::factory()->create(['name' => 'Options Trading', 'slug' => 'options-trading']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Options Trading');
    }
}

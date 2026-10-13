<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Certificate;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Category} */
    private function completedCategory(): array
    {
        $user = User::factory()->create(['name' => 'Asha Patel']);
        $category = Category::factory()->create(['name' => 'Technical Analysis', 'slug' => 'technical-analysis']);

        Video::factory()->count(2)->for($category)->create(['duration_seconds' => 100])
            ->each(fn (Video $video) => $user->fresh()->recordProgress($video, 100));

        return [$user, $category];
    }

    public function test_a_certificate_is_issued_once_and_keeps_its_reference(): void
    {
        [$user, $category] = $this->completedCategory();

        $this->actingAs($user)->get(route('certificates.download', $category))->assertOk();
        $first = Certificate::sole();

        $this->actingAs($user)->get(route('certificates.download', $category))->assertOk();

        $this->assertSame(1, Certificate::count());
        $this->assertSame($first->reference, Certificate::sole()->reference);
    }

    public function test_the_reference_is_an_hmac_not_a_guessable_counter(): void
    {
        [$user, $category] = $this->completedCategory();

        $this->actingAs($user)->get(route('certificates.download', $category));

        $this->assertMatchesRegularExpression('/^TPA-\d{2}-\d{5}-[0-9A-F]{8}$/', Certificate::sole()->reference);
    }

    public function test_anyone_can_verify_a_genuine_reference(): void
    {
        [$user, $category] = $this->completedCategory();
        $this->actingAs($user)->get(route('certificates.download', $category));
        $reference = Certificate::sole()->reference;

        // No session: a recruiter checking a reference is not a customer.
        $this->get(route('certificates.verify', $reference))
            ->assertOk()
            ->assertSee('This certificate is genuine')
            ->assertSee('Asha Patel')
            ->assertSee('Technical Analysis')
            ->assertSee($reference)
            ->assertSee('2 lessons');
    }

    public function test_the_reference_is_matched_case_insensitively(): void
    {
        [$user, $category] = $this->completedCategory();
        $this->actingAs($user)->get(route('certificates.download', $category));
        $reference = Certificate::sole()->reference;

        $this->get(route('certificates.verify', strtolower($reference)))
            ->assertOk()
            ->assertSee('This certificate is genuine');
    }

    public function test_an_unknown_reference_says_so_plainly(): void
    {
        $this->get(route('certificates.verify', 'TPA-99-99999-DEADBEEF'))
            ->assertOk()
            ->assertSee('No certificate with that reference')
            ->assertSee('TPA-99-99999-DEADBEEF')
            ->assertDontSee('This certificate is genuine');
    }

    public function test_a_reference_shaped_like_ours_but_invented_does_not_verify(): void
    {
        [$user, $category] = $this->completedCategory();
        $this->actingAs($user)->get(route('certificates.download', $category));

        // Right user and category ids, wrong HMAC.
        $forged = sprintf('TPA-%02d-%05d-%s', $category->id, $user->id, 'AAAAAAAA');

        $this->get(route('certificates.verify', $forged))
            ->assertOk()
            ->assertSee('No certificate with that reference');
    }

    public function test_the_certificate_pdf_carries_its_verification_url(): void
    {
        [$user, $category] = $this->completedCategory();
        $this->actingAs($user)->get(route('certificates.download', $category));
        $certificate = Certificate::sole();

        $html = view('certificates.category', [
            'brand' => config('academy.brand'),
            'name' => $user->name,
            'category' => $category->name,
            'lessons' => $certificate->lessons_count,
            'completedAt' => $certificate->completed_at,
            'reference' => $certificate->reference,
            'verifyUrl' => route('certificates.verify', $certificate->reference),
        ])->render();

        $this->assertStringContainsString(route('certificates.verify', $certificate->reference), $html);
    }

    public function test_an_issued_certificate_survives_a_new_lesson_being_published(): void
    {
        [$user, $category] = $this->completedCategory();
        $this->actingAs($user)->get(route('certificates.download', $category))->assertOk();
        $reference = Certificate::sole()->reference;

        // The syllabus grows. What was already awarded stays awarded.
        Video::factory()->for($category)->create(['duration_seconds' => 100]);

        $this->actingAs($user)->get(route('certificates.download', $category))->assertOk();
        $this->get(route('certificates.verify', $reference))->assertOk()->assertSee('This certificate is genuine');

        // ...and it still records the two lessons that existed at the time.
        $this->assertSame(2, Certificate::sole()->lessons_count);
    }

    public function test_someone_who_never_earned_one_still_cannot_get_it(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Video::factory()->count(2)->for($category)->create(['duration_seconds' => 100]);

        $this->actingAs($user)->get(route('certificates.download', $category))->assertForbidden();
        $this->assertDatabaseEmpty('certificates');
    }

    public function test_verification_is_rate_limited(): void
    {
        foreach (range(1, 20) as $ignored) {
            $this->get(route('certificates.verify', 'TPA-99-99999-DEADBEEF'))->assertOk();
        }

        $this->get(route('certificates.verify', 'TPA-99-99999-DEADBEEF'))->assertStatus(429);
    }
}

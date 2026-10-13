<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CertificateController extends Controller
{
    /**
     * Download the certificate for a category the learner has finished.
     *
     * Earning one is checked here, not taken from whatever the dashboard
     * rendered. Once issued it stays downloadable, even if the category later
     * grows a new lesson -- the certificate records what was true at the time.
     */
    public function __invoke(Request $request, Category $category): Response
    {
        $user = $request->user();

        abort_unless($category->videos()->published()->exists(), 404);

        $certificate = Certificate::where('user_id', $user->id)
            ->where('category_id', $category->id)
            ->first();

        if (! $certificate) {
            abort_unless($user->hasCompletedCategory($category), 403);

            $certificate = Certificate::issueFor($user, $category);
        }

        $pdf = Pdf::loadView('certificates.category', [
            'brand' => config('academy.brand'),
            'name' => $user->name,
            'category' => $category->name,
            'lessons' => $certificate->lessons_count,
            'completedAt' => $certificate->completed_at,
            'reference' => $certificate->reference,
            'verifyUrl' => route('certificates.verify', $certificate->reference),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($this->filename($category->name));
    }

    private function filename(string $categoryName): string
    {
        return Str::slug(config('academy.brand').' '.$categoryName.' certificate').'.pdf';
    }
}

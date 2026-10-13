<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\View\View;

class CertificateVerificationController extends Controller
{
    /**
     * Public check that a quoted certificate reference is genuine.
     *
     * Answers 200 either way -- "we have no record of this" is a legitimate
     * answer to a legitimate question, not an error. Rate limited, because
     * this is the one endpoint that confirms whether a reference exists.
     */
    public function __invoke(string $reference): View
    {
        $certificate = Certificate::with(['user', 'category'])
            ->where('reference', strtoupper(trim($reference)))
            ->first();

        return view('certificates.verify', [
            'reference' => $reference,
            'certificate' => $certificate,
        ]);
    }
}

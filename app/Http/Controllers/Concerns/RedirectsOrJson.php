<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsOrJson
{
    /**
     * Finish a write with a flash message and a destination.
     *
     * An XHR submit must NOT be handed a 302: XMLHttpRequest follows redirects
     * itself, which would consume the flash on a request the admin never sees
     * and lose the success toast. So the target is returned as JSON and the
     * browser navigates to it.
     */
    protected function finish(
        Request $request,
        string $target,
        string $message,
        string $key = 'success',
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            $request->session()->flash($key, $message);

            return response()->json(['redirect' => $target]);
        }

        return redirect($target)->with($key, $message);
    }
}

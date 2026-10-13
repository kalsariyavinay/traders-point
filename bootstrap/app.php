<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // Razorpay signs its webhook; it has no session to carry a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'razorpay/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Keep an admin write from ending on a white error page.
         *
         * A failed save used to throw away everything the admin had typed and
         * replace the panel with a stack trace. Now they land back on their
         * form with their input intact and a toast explaining what happened.
         * Laravel has already reported the exception by the time render runs,
         * so nothing is swallowed silently.
         *
         * This belongs here rather than in middleware: Illuminate's routing
         * pipeline turns a controller exception into a response before route
         * middleware ever sees it.
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            // Only admin writes. A GET has no input worth rescuing.
            if ($request->isMethodSafe() || ! $request->is('admin/*')) {
                return null;
            }

            // JSON for the XHR upload, a redirect carrying the toast otherwise.
            $respond = fn (string $message, int $status) => $request->expectsJson()
                ? response()->json(['message' => $message], $status)
                : back()->withInput()->with('error', $message);

            /*
             * Checked before the HttpException bail-out below. Laravel's
             * handler has already turned a TokenMismatchException into an
             * HttpException(419) by the time render callbacks run, so match
             * the status rather than only the original class.
             */
            if ($e instanceof TokenMismatchException
                || ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 419)) {
                return $respond(__('Your session expired while this page was open. Please try again.'), 419);
            }

            // 403/404/422 and friends already say the right thing.
            if ($e instanceof ValidationException
                || $e instanceof HttpExceptionInterface
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException) {
                return null;
            }

            return $respond(
                __('Something went wrong and nothing was saved. The error has been logged — please try again.'),
                500,
            );
        });
    })->create();

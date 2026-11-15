<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyRecaptcha
{
    /**
     * Verify the reCAPTCHA v3 token submitted with the request.
     *
     * The check is skipped only outside production when no key is configured.
     * In production a missing key blocks the request (fail closed).
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $secretKey = config('services.recaptcha.secret_key');

        // Skip verification when not configured (local / test environments)
        if (empty($secretKey)) {
            if (app()->environment('production')) {
                Log::critical('[reCAPTCHA] RECAPTCHA_SECRET_KEY is not set in production.');

                return $this->fail($request, 'reCAPTCHA is not configured. Please contact the administrator.');
            }

            return $next($request);
        }

        // v2 checkbox sends the token in 'g-recaptcha-response'
        $token = $request->input('g-recaptcha-response');

        if (empty($token)) {
            return $this->fail($request, 'Please complete the reCAPTCHA checkbox before submitting.');
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => $secretKey,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            $result = $response->json();

            if (! ($result['success'] ?? false)) {
                Log::warning('[reCAPTCHA] Verification failed', ['errors' => $result['error-codes'] ?? []]);
                return $this->fail($request, 'reCAPTCHA verification failed. Please try again.');
            }

        } catch (\Exception $e) {
            // Fail closed: if Google's API is unreachable we cannot verify the
            // token, so do not let an unverified request through.
            Log::error('[reCAPTCHA] API error: ' . $e->getMessage());

            return $this->fail($request, 'Unable to verify reCAPTCHA right now. Please try again.');
        }

        return $next($request);
    }

    private function fail(Request $request, string $message): mixed
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['recaptcha' => $message])->withInput();
    }
}

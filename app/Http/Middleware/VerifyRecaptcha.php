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
     * If RECAPTCHA_SITE_KEY is not configured (e.g. local dev), the check is
     * skipped so development is never blocked.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $secretKey = config('services.recaptcha.secret_key');

        // Skip verification when not configured (local / test environments)
        if (empty($secretKey)) {
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
            // If Google's API is unreachable, log and let the request through
            // to avoid blocking legitimate users due to a network issue.
            Log::error('[reCAPTCHA] API error: ' . $e->getMessage());
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

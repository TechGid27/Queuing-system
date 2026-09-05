<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IsStudent
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $student = Auth::guard('student')->user();

        if ($student && $student->role === 'student' && $student->isPhoneVerified()) {
            Auth::shouldUse('student');

            return $next($request);
        }

        return redirect()->route('register')->with('error', 'Please verify your phone number first.');
    }
}

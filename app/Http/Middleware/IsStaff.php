<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IsStaff
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        $isAdmin = $user && $user->role === 'admin' && $user->is_active;
        $isActiveStaff = $user
            && $user->role === 'staff'
            && $user->is_active
            && $user->department
            && $user->department->is_active;

        if ($isAdmin || $isActiveStaff) {
            Auth::shouldUse('web');

            return $next($request);
        }

        if ($user) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('error', 'Unauthorized access.');
    }
}

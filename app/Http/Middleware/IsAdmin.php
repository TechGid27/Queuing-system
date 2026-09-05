<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        if ($user && $user->role === 'admin' && $user->is_active) {
            Auth::shouldUse('web');

            return $next($request);
        }

        return redirect()->route('admin.index')->with('error', 'Administrator access is required.');
    }
}

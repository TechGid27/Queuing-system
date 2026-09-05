<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string|null  ...$guards
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                $cannotAccessStaffQueue = $guard === 'web'
                    && $user->role === 'staff'
                    && (! $user->department || ! $user->department->is_active);

                if ($guard === 'web' && (! $user->is_active || $cannotAccessStaffQueue)) {
                    Auth::guard($guard)->logout();

                    continue;
                }

                if ($guard === 'student') {
                    return redirect()->route('student.index');
                }

                return in_array($user->role, ['admin', 'staff'], true)
                    ? redirect()->route('admin.index')
                    : redirect()->route('home');
            }
        }

        return $next($request);
    }
}

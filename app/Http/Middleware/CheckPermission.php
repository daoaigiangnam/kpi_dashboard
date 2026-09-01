<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasPermission($permission)) {
            return $this->forceReauthentication($request);
        }

        // Permissions are database-driven. If an administrator changes the
        // user's group/permissions while the user still has an old session,
        // force a fresh login so the session cannot retain stale authorization.
        $permissionFingerprint = $this->permissionFingerprint($user);
        $sessionFingerprint = $request->session()->get('auth.permission_fingerprint');

        if ($sessionFingerprint !== null && !hash_equals($sessionFingerprint, $permissionFingerprint)) {
            return $this->forceReauthentication($request, 'Your access permissions have changed. Please sign in again.');
        }

        $request->session()->put('auth.permission_fingerprint', $permissionFingerprint);

        return $next($request);
    }

    private function permissionFingerprint(object $user): string
    {
        $codes = $user->isSuperAdmin()
            ? $user->group?->permissions()->orderBy('code')->pluck('code')->all()
            : $user->group?->permissions()->orderBy('code')->pluck('code')->all();

        return hash('sha256', implode('|', $codes ?? []));
    }

    private function forceReauthentication(Request $request, string $message = 'Your session is no longer valid. Please sign in again.'): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}

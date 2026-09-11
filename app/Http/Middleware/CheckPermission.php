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

        if (!$user) {
            return redirect()->route('login')->withErrors(['email' => 'Please sign in to continue.']);
        }

        if (!$user->hasPermission($permission)) {
            abort(403, 'You do not have permission to access this feature.');
        }

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
        return hash('sha256', implode('|', $this->assignedPermissionCodes($user)));
    }

    private function assignedPermissionCodes(object $user): array
    {
        return $user->group?->permissions()->orderBy('code')->pluck('code')->all() ?? [];
    }

    private function forceReauthentication(Request $request, string $message): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}

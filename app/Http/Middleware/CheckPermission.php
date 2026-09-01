<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
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

        // A permission can be created in the database before its actual route
        // is implemented. Do not keep an authenticated session carrying such
        // an authorization grant for non-super-admin users; require a clean
        // login instead of leaving stale/partial authorization state behind.
        if (!$user->isSuperAdmin()) {
            $unimplemented = array_diff($this->assignedPermissionCodes($user), $this->implementedPermissionCodes($request));

            if ($unimplemented !== []) {
                return $this->forceReauthentication($request, 'Your access permissions are being updated. Please sign in again.');
            }
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

    private function implementedPermissionCodes(Request $request): array
    {
        $codes = [];
        $router = $request->getRouteResolver() ? $request->getRouteResolver()() : null;

        if (!$router instanceof \Illuminate\Routing\Route) {
            return $codes;
        }

        foreach ($router->gatherMiddleware() as $middleware) {
            if (str_starts_with($middleware, 'permission:')) {
                $code = trim(substr($middleware, strlen('permission:')));
                if ($code !== '') {
                    $codes[] = $code;
                }
            }
        }

        // The current route only exposes its own permission. To determine the
        // full implemented permission catalog, inspect Laravel's route table.
        $routeCollection = app(Router::class)->getRoutes();
        foreach ($routeCollection as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (str_starts_with($middleware, 'permission:')) {
                    $code = trim(substr($middleware, strlen('permission:')));
                    if ($code !== '') {
                        $codes[] = $code;
                    }
                }
            }
        }

        return array_values(array_unique($codes));
    }

    private function forceReauthentication(Request $request, string $message = 'Your session is no longer valid. Please sign in again.'): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}

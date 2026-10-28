<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RestrictedAccess
{
    /**
     * Routes that should be accessible without authentication
     * when the instance is restricted.
     *
     * @var array
     */
    protected $allowedRoutes = [
        'login',
        'login/2fa',
        'logout',
        'password*',
        'loginAs*',
        'auth/oidc*',
        'auth/remote*',
        'auth/mastodon*',
        'auth/raw*',
        'auth/forgot*',
        'auth/pci*',
        'auth/sign_up*',
        'auth/invite*',
        'oauth/token',
        'oauth/authorize',
        'i/app-email-verify',
        'i/app-email-resend',
        'api/v1/apps',
        'api/v1/instance',
        'api/v1.1/auth/invite/*',
        'api/v2.1/config',
        'api/service/health-check',
        'api/nodeinfo*',
        'storage/*',
        '.well-known/*',
    ];

    /**
     * Routes that are only allowed when open registration is enabled.
     *
     * @var array
     */
    protected $registrationRoutes = [
        'register',
        'register/*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (config('instance.restricted.enabled')) {
            // API clients (mobile apps) authenticate with a token instead of a session
            if (! Auth::guard($guard)->check() && ! Auth::guard('api')->check()) {
                $allowed = $this->allowedRoutes;

                if (config('pixelfed.open_registration')) {
                    $allowed = array_merge($allowed, $this->registrationRoutes);
                }

                if (! $request->is($allowed)) {
                    Log::debug('RestrictedAccess: Request path', ['path' => $request->path()]);

                    return redirect('/login');
                }
            }
        }

        return $next($request);
    }
}

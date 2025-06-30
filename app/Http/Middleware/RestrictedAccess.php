<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class RestrictedAccess
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @param string|null              $guard
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if(config('instance.restricted.enabled')) {
            if (!Auth::guard($guard)->check() && !Auth::guard('api')->check()) {
                $p = [
                    'login',
                    'oauth/token',
                    'oauth/authorize',
                    'auth/invite/*',
                    'api/v1/apps',
                    'api/v1.1/auth/invite/user/re',
                    'password*',
                    'api/service/health-check',
                    'storage/*',
                ];
                $userAgent = request()->header('User-Agent', 'unknown');
                if(str_contains($userAgent, 'Pixelfed/')){
                    array_push($p,
                        'api/nodeinfo*',
                    );
                }
                if(!$request->is($p)) {
                    Log::debug('RestrictedAccess: Request path', ['path' => $request->path()]);
                    return redirect('/login');
                }
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Util\Site;

use App\Services\InstanceService;
use App\User;
use Illuminate\Support\Facades\Cache;

class Nodeinfo
{
    public static function get()
    {
        $res = Cache::remember('api:nodeinfo', now()->addMinutes(60), function () {
            $activeHalfYear = self::activeUsersHalfYear();
            $activeMonth = self::activeUsersMonthly();

            $users = Cache::remember('api:nodeinfo:users', now()->addMinutes(60), function () {
                return User::whereNull('status')->count(); # Only get null status - these are the "active" users
            });

            if ((bool) config('instance.glitch.real_stat_count') == true) {
                $postCount = InstanceService::totalRealLocalStatuses();
            } else {
                $postCount = InstanceService::totalLocalStatuses();
            }

            $features = ['features' => \App\Util\Site\Config::get()['features']];
            unset($features['features']['hls']);

            return [
                'software' => [
                    'name' => 'pixelfed',
                    'version' => config('pixelfed.version'),
                ],
                'version' => '2.0',
            ];
        });

        return $res;
    }

    public static function wellKnown()
    {
        return [
            'links' => [
                [
                    'href' => config('pixelfed.nodeinfo.url'),
                    'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
                ],
            ],
        ];
    }

    public static function activeUsersMonthly()
    {
        return Cache::remember('api:nodeinfo:active-users-monthly', now()->addMinutes(60), function () {
            return User::withTrashed()
                ->select('last_active_at, updated_at')
                ->where('updated_at', '>', now()->subWeeks(5))
                ->orWhere('last_active_at', '>', now()->subWeeks(5))
                ->count();
        });
    }

    public static function activeUsersHalfYear()
    {
        return Cache::remember('api:nodeinfo:active-users-half-year', now()->addMinutes(60), function () {
            return User::withTrashed()
                ->select('last_active_at, updated_at')
                ->where('last_active_at', '>', now()->subMonths(6))
                ->orWhere('updated_at', '>', now()->subMonths(6))
                ->count();
        });
    }
}

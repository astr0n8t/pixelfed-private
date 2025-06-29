<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DiscoverService
{
	public static function getDailyIdPool()
	{
		$min_id = SnowflakeService::byDate(now()->subMonths(3));
		$sqld = (config('database.default') == 'mysql' || config('database.default') == 'mariadb');
		return DB::table('statuses')
			->whereNull('uri')
			->whereType('photo')
			->whereIsNsfw(false)
			->whereVisibility('public')
			->when($sqld, function($q, $sqld) {
				return $q->groupBy('profile_id');
			})
			->where('id', '>', $min_id)
			->inRandomOrder()
			->take(300)
			->pluck('id');
	}

	public static function getForYou()
	{
		return Cache::remember('pf:services:discover:for-you', now()->addMinutes(60), function() {
			return self::getDailyIdPool();
		});
	}
}

<?php

namespace App\Services;

use App\Avatar;
use App\Contact;
use App\FailedJob;
use App\Instance;
use App\Media;
use App\Profile;
use App\Report;
use App\Status;
use App\User;
use App\Util\Lexer\PrettyNumber;
use Cache;
use DateInterval;
use DatePeriod;
use DB;

class AdminStatsService
{
    public static function get()
    {
        return array_merge(
            self::recentData(),
            self::additionalData(),
            self::postsGraph()
        );
    }

    public static function summary()
    {
        return array_merge(
            self::recentData(),
            self::additionalDataSummary(),
        );
    }

    public static function storage()
    {
        return Cache::remember('admin:dashboard:storage:stats', now()->addMinutes(60), function () {
            $res = [];

            $res['last_updated'] = str_replace('+00:00', 'Z', now()->format(DATE_RFC3339_EXTENDED));

            $avatars = Avatar::count();
            $avatarsLocal = Avatar::whereNull('cdn_url')->count();
            $res['avatar'] = [
                'count' => $avatars,
                'local_count' => $avatarsLocal,
                'cloud_count' => ($avatars - $avatarsLocal),
                'total_sum' => Avatar::sum('size'),
            ];

            $media = Media::count();
            $mediaSum = Media::sum('size');
            $mediaLocalSum = Media::whereNotNull('user_id')->sum('size');
            $mediaLocal = Media::whereNotNull('user_id')->count();
            $res['media'] = [
                'count' => $media,
                'local_count' => $mediaLocal,
                'cloud_count' => ($media - $mediaLocal),
                'total_sum' => $mediaSum,
                'local_sum' => $mediaLocalSum,
                'local_30d' => Media::whereNotNull('user_id')->where('created_at', '>', now()->subDays(30))->sum('size'),
                'cloud_30d' => Media::whereNull('user_id')->where('created_at', '>', now()->subDays(30))->sum('size'),
                'cloud_sum' => ($mediaSum - $mediaLocalSum),
                'avg_size' => Media::avg('size'),
                'avg_mime' => Media::groupBy('mime')->orderByRaw('COUNT(*) DESC')->first()->mime,
            ];

            return $res;
        });
    }

    protected static function recentData()
    {
        $day = config('database.default') == 'pgsql' ? 'DATE_PART(\'day\',' : 'day(';
        $ttl = now()->addMinutes(15);

        return Cache::remember('admin:dashboard:home:data:v0:15min', now()->addMinutes(60), function () {
            return [
                'contact' => PrettyNumber::convert(Contact::whereNull('read_at')->count()),
                'contact_monthly' => PrettyNumber::convert(Contact::whereNull('read_at')->where('created_at', '>', now()->subMonth())->count()),
                'reports' => PrettyNumber::convert(Report::whereNull('admin_seen')->count()),
                'reports_monthly' => PrettyNumber::convert(Report::whereNull('admin_seen')->where('created_at', '>', now()->subMonth())->count()),
            ];
        });
    }

    protected static function additionalData()
    {
        $ttl = now()->addHours(24);

        return Cache::remember('admin:dashboard:home:data:v0:24hr', now()->addMinutes(60), function () {
            return [
                'failedjobs' => PrettyNumber::convert(FailedJob::where('failed_at', '>=', \Carbon\Carbon::now()->subDay())->count()),
                'statuses' => PrettyNumber::convert(intval(StatusService::totalLocalStatuses())),
                'real_statuses' => PrettyNumber::convert(intval(StatusService::totalRealLocalStatuses())),
                'statuses_monthly' => PrettyNumber::convert(Status::where('created_at', '>', now()->subMonth())->count()),
                'real_statuses_monthly' => PrettyNumber::convert(Status::where('url', '=', 'null')->where('created_at', '>', now()->subMonth())->where('reblog_of_id', '=', 'null')->where('in_reply_to_id', '=', 'null')->count()),
                'profiles' => PrettyNumber::convert(Profile::count()),
                'users' => PrettyNumber::convert(User::whereNull('status')->count()),
                'users_monthly' => PrettyNumber::convert(User::where('created_at', '>', now()->subMonth())->whereNull('status')->count()),
                'instances' => PrettyNumber::convert(Instance::count()),
                'media' => PrettyNumber::convert(Media::count()),
                'storage' => Media::sum('size'),
            ];
        });
    }

    protected static function additionalDataSummary()
    {
        $ttl = now()->addHours(24);

        return Cache::remember('admin:dashboard:home:data-summary:v0:24hr', now()->addMinutes(60), function () {
            return [
                'statuses' => PrettyNumber::convert(intval(StatusService::totalLocalStatuses())),
                'real_statuses' => PrettyNumber::convert(intval(StatusService::totalRealLocalStatuses())),
                'profiles' => PrettyNumber::convert(Profile::count()),
                'users' => PrettyNumber::convert(User::whereNull('status')->count()),
                'instances' => PrettyNumber::convert(Instance::count()),
            ];
        });
    }

    protected static function postsGraph()
    {
        $ttl = now()->addHours(12);

        return Cache::remember('admin:dashboard:home:data-postsGraph:v0.1:24hr', now()->addMinutes(60), function () {
            $gb = config('database.default') == 'pgsql' ? ['statuses.id', 'created_at'] : DB::raw('Date(created_at)');
            $s = Status::selectRaw('Date(created_at) as date, count(statuses.id) as count')
                ->where('created_at', '>=', now()->subWeek())
                ->groupBy($gb)
                ->orderBy('created_at', 'DESC')
                ->pluck('count', 'date');

            $begin = now()->subWeek();
            $end = now();
            $interval = new DateInterval('P1D');
            $daterange = new DatePeriod($begin, $interval, $end);
            $dates = [];
            foreach ($daterange as $date) {
                $dates[$date->format('Y-m-d')] = 0;
            }

            $dates = collect($dates)->merge($s);

            $s = Status::selectRaw('Date(created_at) as date, count(statuses.id) as count')
                ->where('created_at', '>=', now()->subWeeks(2))
                ->where('created_at', '<=', now()->subWeeks(1))
                ->groupBy($gb)
                ->orderBy('created_at', 'DESC')
                ->pluck('count', 'date');

            $begin = now()->subWeeks(2);
            $end = now()->subWeeks(1);
            $interval = new DateInterval('P1D');
            $daterange = new DatePeriod($begin, $interval, $end);
            $lw = [];
            foreach ($daterange as $date) {
                $lw[$date->format('Y-m-d')] = 0;
            }

            $lw = collect($lw)->merge($s);

            return [
                'posts_this_week' => $dates->values(),
                'posts_last_week' => $lw->values(),
            ];
        });
    }
}

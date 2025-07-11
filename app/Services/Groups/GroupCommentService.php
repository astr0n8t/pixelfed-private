<?php

namespace App\Services\Groups;

use App\Models\GroupComment;
use Cache;
use Illuminate\Support\Facades\Redis;
use League\Fractal;
use League\Fractal\Serializer\ArraySerializer;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use App\Transformer\Api\GroupPostTransformer;

class GroupCommentService
{
    const CACHE_KEY = 'pf:services:groups:comment:';

    public static function key($gid, $pid)
    {
        return self::CACHE_KEY . $gid . ':' . $pid;
    }

    public static function get($gid, $pid)
    {
        return Cache::remember(self::key($gid, $pid), now()->addMinutes(60), function() use($gid, $pid) {
            $gp = GroupComment::whereGroupId($gid)->find($pid);

            if(!$gp) {
                return null;
            }

            $fractal = new Fractal\Manager();
            $fractal->setSerializer(new ArraySerializer());
            $resource = new Fractal\Resource\Item($gp, new GroupPostTransformer());
            $res = $fractal->createData($resource)->toArray();

            $res['pf_type'] = 'group:post:comment';
            $res['url'] = $gp->url();
            // if($gp['type'] == 'poll') {
            //  $status['poll'] = PollService::get($status['id']);
            // }
            //$status['account']['url'] = url("/groups/{$gp['group_id']}/user/{$status['account']['id']}");
            return $res;
        });
    }

    public static function del($gid, $pid)
    {
        return Cache::forget(self::key($gid, $pid));
    }
}

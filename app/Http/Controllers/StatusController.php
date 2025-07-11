<?php

namespace App\Http\Controllers;

use App\AccountInterstitial;
use App\Jobs\SharePipeline\SharePipeline;
use App\Jobs\SharePipeline\UndoSharePipeline;
use App\Jobs\StatusPipeline\RemoteStatusDelete;
use App\Jobs\StatusPipeline\StatusDelete;
use App\Profile;
use App\Services\AccountService;
use App\Services\HashidService;
use App\Services\ReblogService;
use App\Services\StatusService;
use App\Status;
use App\StatusView;
use App\Transformer\ActivityPub\Verb\Note;
use App\Transformer\ActivityPub\Verb\Question;
use App\Util\Media\License;
use Auth;
use Cache;
use DB;
use Illuminate\Http\Request;
use League\Fractal;

class StatusController extends Controller
{
    public function show(Request $request, $username, $id)
    {
        // redirect authed users to Metro 2.0
        if ($request->user()) {
            // unless they force static view
            if (! $request->has('fs') || $request->input('fs') != '1') {
                return redirect('/i/web/post/'.$id);
            }
        }

        $status = StatusService::get($id, false);

        abort_if(
            ! $status ||
            ! isset($status['account'], $status['account']['username']) ||
            $status['account']['username'] != $username ||
            isset($status['reblog']), 404);

        abort_if(! in_array($status['visibility'], ['public', 'unlisted']) && ! $request->user(), 403, 'Invalid permission');

        if ($request->wantsJson() && (bool) config_cache('federation.activitypub.enabled')) {
            return $this->showActivityPub($request, $status);
        }

        $user = Profile::whereNull('domain')->whereUsername($username)->firstOrFail();
        if ($user->status != null) {
            return ProfileController::accountCheck($user);
        }

        $status = Status::whereProfileId($user->id)
            ->whereNull('reblog_of_id')
            ->whereIn('scope', ['public', 'unlisted', 'private'])
            ->findOrFail($id);

        if ($status->uri || $status->url) {
            $url = $status->uri ?? $status->url;
            if (ends_with($url, '/activity')) {
                $url = str_replace('/activity', '', $url);
            }

            return redirect($url);
        }

        if ($status->visibility == 'private' || $user->is_private) {
            if (! Auth::check()) {
                abort(404);
            }
            $pid = Auth::user()->profile;
            if ($user->followedBy($pid) == false && $user->id !== $pid->id && Auth::user()->is_admin == false) {
                abort(404);
            }
        }

        if ($status->type == 'archived') {
            if (Auth::user()->profile_id !== $status->profile_id) {
                abort(404);
            }
        }

        $template = $status->in_reply_to_id ? 'status.reply' : 'status.show';

        return view($template, compact('user', 'status'));
    }

    public function shortcodeRedirect(Request $request, $id)
    {
        $hid = HashidService::decode($id);
        abort_if(! $hid, 404);

        return redirect('/i/web/post/'.$hid);
    }

    public function showId(int $id)
    {
        abort(404);
        $status = Status::whereNull('reblog_of_id')
            ->whereIn('scope', ['public', 'unlisted'])
            ->findOrFail($id);

        return redirect($status->url());
    }

    public function showEmbed(Request $request, $username, int $id)
    {
        if (! (bool) config_cache('instance.embed.post')) {
            $res = view('status.embed-removed');

            return response($res)->withHeaders(['X-Frame-Options' => 'ALLOWALL']);
        }

        $status = StatusService::get($id);

        if (
            ! $status ||
            ! isset($status['account'], $status['account']['id'], $status['local']) ||
            ! $status['local'] ||
            strtolower($status['account']['username']) !== strtolower($username) ||
            isset($status['account']['moved'], $status['account']['moved']['id'])
        ) {
            $content = view('status.embed-removed');

            return response($content, 404)->header('X-Frame-Options', 'ALLOWALL');
        }

        $profile = AccountService::get($status['account']['id'], true);

        if (! $profile || $profile['locked'] || ! $profile['local']) {
            $content = view('status.embed-removed');

            return response($content)->header('X-Frame-Options', 'ALLOWALL');
        }

        $embedCheck = AccountService::canEmbed($profile['id']);

        if (! $embedCheck) {
            $content = view('status.embed-removed');

            return response($content)->header('X-Frame-Options', 'ALLOWALL');
        }

        $aiCheck = Cache::remember('profile:ai-check:spam-login:'.$profile['id'], now()->addMinutes(60), function () use ($profile) {
            $user = Profile::find($profile['id']);
            if (! $user) {
                return true;
            }
            $exists = AccountInterstitial::whereUserId($user->user_id)->where('is_spam', 1)->count();
            if ($exists) {
                return true;
            }

            return false;
        });

        if ($aiCheck) {
            $res = view('status.embed-removed');

            return response($res)->withHeaders(['X-Frame-Options' => 'ALLOWALL']);
        }

        $status = StatusService::get($id);

        if (
            ! $status ||
            ! isset($status['account'], $status['account']['id']) ||
            intval($status['account']['id']) !== intval($profile['id']) ||
            $status['sensitive'] ||
            $status['visibility'] !== 'public' ||
            ! in_array($status['pf_type'], ['photo', 'photo:album'])
        ) {
            $content = view('status.embed-removed');

            return response($content)->header('X-Frame-Options', 'ALLOWALL');
        }

        $showLikes = $request->filled('likes') && $request->likes == true;
        $showCaption = $request->filled('caption') && $request->caption !== false;
        $layout = $request->filled('layout') && $request->layout == 'compact' ? 'compact' : 'full';
        $content = view('status.embed', compact('status', 'showLikes', 'showCaption', 'layout'));

        return response($content)->withHeaders(['X-Frame-Options' => 'ALLOWALL']);
    }

    public function showObject(Request $request, $username, int $id)
    {
        $user = Profile::whereNull('domain')->whereUsername($username)->firstOrFail();

        if ($user->status != null) {
            return ProfileController::accountCheck($user);
        }

        $status = Status::whereProfileId($user->id)
            ->whereNotIn('visibility', ['draft', 'direct'])
            ->findOrFail($id);

        abort_if($status->uri, 404);

        if ($status->visibility == 'private' || $user->is_private) {
            if (! Auth::check()) {
                abort(403);
            }
            $pid = Auth::user()->profile;
            if ($user->followedBy($pid) == false && $user->id !== $pid->id) {
                abort(403);
            }
        }

        return $this->showActivityPub($request, $status);
    }

    public function compose()
    {
        $this->authCheck();

        return view('status.compose');
    }

    public function store(Request $request) {}

    public function delete(Request $request)
    {
        $this->authCheck();

        $this->validate($request, [
            'item' => 'required|integer|min:1',
        ]);

        $status = Status::findOrFail($request->input('item'));

        $user = Auth::user();

        if ($status->profile_id != $user->profile->id &&
            $user->is_admin == true &&
            $status->uri == null
        ) {
            $media = $status->media;

            $ai = new AccountInterstitial;
            $ai->user_id = $status->profile->user_id;
            $ai->type = 'post.removed';
            $ai->view = 'account.moderation.post.removed';
            $ai->item_type = 'App\Status';
            $ai->item_id = $status->id;
            $ai->has_media = (bool) $media->count();
            $ai->blurhash = $media->count() ? $media->first()->blurhash : null;
            $ai->meta = json_encode([
                'caption' => $status->caption,
                'created_at' => $status->created_at,
                'type' => $status->type,
                'url' => $status->url(),
                'is_nsfw' => $status->is_nsfw,
                'scope' => $status->scope,
                'reblog' => $status->reblog_of_id,
                'likes_count' => $status->likes_count,
                'reblogs_count' => $status->reblogs_count,
            ]);
            $ai->save();

            $u = $status->profile->user;
            $u->has_interstitial = true;
            $u->save();
        }

        if ($status->in_reply_to_id) {
            $parent = Status::find($status->in_reply_to_id);
            if ($parent && ($parent->profile_id == $user->profile_id) || ($status->profile_id == $user->profile_id) || $user->is_admin) {
                Cache::forget('_api:statuses:recent_9:'.$status->profile_id);
                Cache::forget('profile:status_count:'.$status->profile_id);
                Cache::forget('profile:embed:'.$status->profile_id);
                StatusService::del($status->id, true);
                Cache::forget('profile:status_count:'.$status->profile_id);
                $status->uri ? RemoteStatusDelete::dispatch($status) : StatusDelete::dispatch($status);
            }
        } elseif ($status->profile_id == $user->profile_id || $user->is_admin == true) {
            Cache::forget('_api:statuses:recent_9:'.$status->profile_id);
            Cache::forget('profile:status_count:'.$status->profile_id);
            Cache::forget('profile:embed:'.$status->profile_id);
            StatusService::del($status->id, true);
            Cache::forget('profile:status_count:'.$status->profile_id);
            $status->uri ? RemoteStatusDelete::dispatch($status) : StatusDelete::dispatch($status);
        }

        if ($request->wantsJson()) {
            return response()->json(['Status successfully deleted.']);
        } else {
            return redirect($user->url());
        }
    }

    public function storeShare(Request $request)
    {
        $this->authCheck();

        $this->validate($request, [
            'item' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $profile = $user->profile;
        $status = Status::whereScope('public')
            ->findOrFail($request->input('item'));
        $statusAccount = AccountService::get($status->profile_id);
        abort_if(! $statusAccount || isset($statusAccount['moved'], $statusAccount['moved']['id']), 422, 'Account moved');

        $count = $status->reblogs_count;
        $defaultCaption = config_cache('database.default') === 'mysql' ? null : "";
        $exists = Status::whereProfileId(Auth::user()->profile->id)
            ->whereReblogOfId($status->id)
            ->exists();
        if ($exists == true) {
            $shares = Status::whereProfileId(Auth::user()->profile->id)
                ->whereReblogOfId($status->id)
                ->get();
            foreach ($shares as $share) {
                UndoSharePipeline::dispatch($share);
                ReblogService::del($profile->id, $status->id);
                $count--;
            }
        } else {
            $share = new Status;
            $share->caption = $defaultCaption;
            $share->rendered = $defaultCaption;
            $share->profile_id = $profile->id;
            $share->reblog_of_id = $status->id;
            $share->in_reply_to_profile_id = $status->profile_id;
            $share->type = 'share';
            $share->save();
            $count++;
            SharePipeline::dispatch($share);
            ReblogService::add($profile->id, $status->id);
        }

        Cache::forget('status:'.$status->id.':sharedby:userid:'.$user->id);
        StatusService::del($status->id);

        if ($request->ajax()) {
            $response = ['code' => 200, 'msg' => 'Share saved', 'count' => $count];
        } else {
            $response = redirect($status->url());
        }

        return $response;
    }

    public function showActivityPub(Request $request, $status)
    {
        $key = 'pf:status:ap:v1:sid:'.$status['id'];

        return Cache::remember($key, now()->addMinutes(60), function () use ($status) {
            $status = Status::findOrFail($status['id']);
            $object = $status->type == 'poll' ? new Question : new Note;
            $fractal = new Fractal\Manager;
            $resource = new Fractal\Resource\Item($status, $object);
            $res = $fractal->createData($resource)->toArray();

            return response()->json($res['data'], 200, ['Content-Type' => 'application/activity+json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        });
    }

    public function edit(Request $request, $username, $id)
    {
        $this->authCheck();
        $user = Auth::user()->profile;
        $status = Status::whereProfileId($user->id)
            ->with(['media'])
            ->findOrFail($id);
        $licenses = License::get();

        return view('status.edit', compact('user', 'status', 'licenses'));
    }

    public function editStore(Request $request, $username, $id)
    {
        $this->authCheck();
        $user = Auth::user()->profile;
        $status = Status::whereProfileId($user->id)
            ->with(['media'])
            ->findOrFail($id);

        $this->validate($request, [
            'license' => 'nullable|integer|min:1|max:16',
        ]);

        $licenseId = $request->input('license');

        $status->media->each(function ($media) use ($licenseId) {
            $media->license = $licenseId;
            $media->save();
            Cache::forget('status:transformer:media:attachments:'.$media->status_id);
        });

        return redirect($status->url());
    }

    protected function authCheck()
    {
        if (Auth::check() == false) {
            abort(403);
        }
    }

    protected function validateVisibility($visibility)
    {
        $allowed = ['public', 'unlisted', 'private'];

        return in_array($visibility, $allowed) ? $visibility : 'public';
    }

    public static function mimeTypeCheck($mimes)
    {
        $allowed = explode(',', config_cache('pixelfed.media_types'));
        $count = count($mimes);
        $photos = 0;
        $videos = 0;
        foreach ($mimes as $mime) {
            if (in_array($mime, $allowed) == false && $mime !== 'video/mp4') {
                continue;
            }
            if (str_contains($mime, 'image/')) {
                $photos++;
            }
            if (str_contains($mime, 'video/')) {
                $videos++;
            }
        }
        if ($photos == 1 && $videos == 0) {
            return 'photo';
        }
        if ($videos == 1 && $photos == 0) {
            return 'video';
        }
        if ($photos > 1 && $videos == 0) {
            return 'photo:album';
        }
        if ($videos > 1 && $photos == 0) {
            return 'video:album';
        }
        if ($photos >= 1 && $videos >= 1) {
            return 'photo:video:album';
        }

        return 'text';
    }

    public function toggleVisibility(Request $request)
    {
        $this->authCheck();
        $this->validate($request, [
            'item' => 'required|string|min:1|max:20',
            'disableComments' => 'required|boolean',
        ]);

        $user = Auth::user();
        $id = $request->input('item');
        $state = $request->input('disableComments');

        $status = Status::findOrFail($id);

        if ($status->profile_id != $user->profile->id && $user->is_admin == false) {
            abort(403);
        }

        $status->comments_disabled = $status->comments_disabled == true ? false : true;
        $status->save();

        return response()->json([200]);
    }

    public function storeView(Request $request)
    {
        abort_if(! $request->user(), 403);

        $views = $request->input('_v');
        $uid = $request->user()->profile_id;

        if (empty($views) || ! is_array($views)) {
            return response()->json(0);
        }

        Cache::forget('profile:home-timeline-cursor:'.$request->user()->id);

        foreach ($views as $view) {
            if (! isset($view['sid']) || ! isset($view['pid'])) {
                continue;
            }
            DB::transaction(function () use ($view, $uid) {
                StatusView::firstOrCreate([
                    'status_id' => $view['sid'],
                    'status_profile_id' => $view['pid'],
                    'profile_id' => $uid,
                ]);
            });
        }

        return response()->json(1);
    }
}

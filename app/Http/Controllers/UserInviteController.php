<?php

namespace App\Http\Controllers;

use App\Jobs\UserInvitePipeline\DispatchUserInvitePipeline;
use App\Models\User;
use App\Models\UserInvite;
use App\Rules\ValidUsername;
use App\Services\EmailService;
use App\Services\EmailVerificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Purify;

class UserInviteController extends Controller
{
    protected function authPreflight(Request $request, $maxUserCheck = false): void
    {
        abort_unless($request->user(), 404);
        abort_unless(config('pixelfed.user_invites.enabled'), 404);

        if ($maxUserCheck && config('pixelfed.enforce_max_users')) {
            $count = User::where(function ($q) {
                return $q->whereNull('status')->orWhereNotIn('status', ['deleted', 'delete']);
            })->count();
            $limit = (int) config('pixelfed.max_users');

            abort_if($limit && $limit <= $count, 404);
        }
    }

    protected function findValidInvite(Request $request): UserInvite
    {
        abort_unless(config('pixelfed.user_invites.enabled'), 404);

        $invite = UserInvite::where('token', $request->input('token'))
            ->where('valid_until', '>=', now())
            ->first();
        abort_if(! $invite, 404);
        abort_if($invite->used_at != null, 400, 'Invite already used.');

        return $invite;
    }

    protected function emailRules(): array
    {
        return [
            'required',
            'string',
            'email:strict,rfc,dns,spoof,filter',
            'max:255',
            'unique:users',
            function ($attribute, $value, $fail) {
                if (EmailService::isBanned($value)) {
                    return $fail('Email is invalid.');
                }
            },
        ];
    }

    public function get(Request $request): JsonResponse|RedirectResponse
    {
        $this->authPreflight($request);
        $invites = UserInvite::whereUserId(Auth::id())->paginate(10);

        if ($request->wantsJson()) {
            return response()->json($invites);
        }

        return redirect(route('invites.home'));
    }

    public function create(Request $request): JsonResponse|RedirectResponse
    {
        $this->authPreflight($request, true);
        $this->validate($request, [
            'email' => 'required|email|unique:users|unique:user_invites',
            'message' => 'nullable|string|max:500',
        ]);

        $email = $request->input('email');
        $userLimit = (int) config('pixelfed.user_invites.limit.total');

        abort_if($userLimit > 0 && UserInvite::whereUserId(Auth::id())->count() >= $userLimit, 400, 'Invite limit reached.');
        abort_if(EmailService::isBanned($email), 400);

        $invite = new UserInvite;
        $invite->user_id = Auth::id();
        $invite->profile_id = $request->user()->profile_id;
        $invite->email = $email;
        $invite->message = $request->input('message');
        $invite->key = Str::random(random_int(6, 9)).'_'.Str::random(random_int(14, 20)).'_'.Str::random(random_int(32, 64));
        $invite->token = Str::random(32);
        $invite->valid_until = now()->addDays(30);
        $invite->save();

        DispatchUserInvitePipeline::dispatch($invite);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'invite' => $invite]);
        }

        return redirect(route('invites.home'));
    }

    public function delete(Request $request): JsonResponse|RedirectResponse
    {
        $this->authPreflight($request);
        $this->validate($request, [
            'id' => 'required',
        ]);

        UserInvite::whereUserId($request->user()->id)
            ->findOrFail($request->input('id'))
            ->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect(route('invites.home'));
    }

    public function index(Request $request, $code): RedirectResponse|View
    {
        abort_unless(config('pixelfed.user_invites.enabled'), 404);

        if ($request->user()) {
            return redirect('/');
        }

        return view('invite.user_invite', ['code' => $code]);
    }

    public function apiVerifyCheck(Request $request): JsonResponse
    {
        $this->validate($request, [
            'token' => 'required',
        ]);

        $invite = $this->findValidInvite($request);

        return response()->json([
            'message' => $invite->message,
        ]);
    }

    public function apiUsernameCheck(Request $request): JsonResponse
    {
        $this->validate($request, [
            'token' => 'required',
            'username' => 'required',
        ]);

        $this->findValidInvite($request);

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'min:2', 'max:30', 'unique:users', new ValidUsername],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        return response()->json([]);
    }

    public function apiEmailCheck(Request $request): JsonResponse
    {
        $this->validate($request, [
            'token' => 'required',
            'email' => 'required',
        ]);

        $this->findValidInvite($request);

        $validator = Validator::make($request->all(), [
            'email' => $this->emailRules(),
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        return response()->json([]);
    }

    public function apiRegister(Request $request): JsonResponse|RedirectResponse
    {
        $this->validate($request, [
            'token' => 'required',
            'username' => ['required', 'min:2', 'max:30', 'unique:users', new ValidUsername],
            'name' => 'nullable|string|max:'.config('pixelfed.max_name_length'),
            'email' => $this->emailRules(),
            'password' => 'required|string|min:'.config('pixelfed.min_password_length'),
            'password_confirm' => 'required|same:password',
        ]);

        $invite = $this->findValidInvite($request);
        $invite->used_at = now();
        $invite->save();

        event(new Registered($user = User::create([
            'name' => Purify::clean($request->input('name')) ?? $request->input('username'),
            'username' => $request->input('username'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
        ])));

        sleep(5);

        // The invite was mailed to this address, so it is already verified
        if ($request->input('email') === $invite->email) {
            $user->email_verified_at = now();
            $user->save();
        } else {
            EmailVerificationService::send($user);
        }

        if (Auth::attempt([
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ])) {
            $request->session()->regenerate();

            return redirect()->intended('/');
        }

        return response()->json([], 400);
    }
}

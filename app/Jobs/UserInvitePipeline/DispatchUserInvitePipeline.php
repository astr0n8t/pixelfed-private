<?php

namespace App\Jobs\UserInvitePipeline;

use App\Mail\UserInviteMessage;
use App\Models\UserInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class DispatchUserInvitePipeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public UserInvite $invite,
    ) {}

    public function handle(): void
    {
        Mail::to($this->invite->email)->send(new UserInviteMessage($this->invite));
    }
}

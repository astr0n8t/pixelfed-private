<?php

namespace App\Mail;

use App\Models\UserInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInviteMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly UserInvite $invite,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'ve been invited to join '.config('app.name').'!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user.invite',
        );
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verificationLink;

    public function __construct(
        public User $user,
        string $verificationLink = ''
    ) {
        $this->verificationLink = $verificationLink;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'NextScout\'a Hoş Geldiniz! E-postanızı Doğrulayın',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'user'             => $this->user,
                'verificationLink' => $this->verificationLink,
                'appName'          => config('app.name', 'NextScout'),
            ],
        );
    }
}

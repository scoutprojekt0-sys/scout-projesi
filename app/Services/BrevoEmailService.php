<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use RuntimeException;

class BrevoEmailService
{
    private string $apiKey;
    private string $baseUrl;
    private string $senderEmail;
    private string $senderName;

    public function __construct()
    {
        $this->apiKey = (string) config('services.brevo.api_key', '');
        $this->baseUrl = rtrim((string) config('services.brevo.base_url', 'https://api.brevo.com/v3'), '/');
        $this->senderEmail = (string) config('services.brevo.sender_email', config('mail.from.address', ''));
        $this->senderName = (string) config('services.brevo.sender_name', config('mail.from.name', 'NextScout'));
    }

    public function sendWelcomeEmail(User $user, string $verificationLink): void
    {
        $html = View::make('emails.welcome', [
            'user' => $user,
            'verificationLink' => $verificationLink,
            'appName' => config('app.name', 'NextScout'),
        ])->render();

        $this->sendEmail(
            toEmail: (string) $user->email,
            toName: (string) $user->name,
            subject: "NextScout'a Hos Geldiniz! E-postanizi Dogrulayin",
            htmlContent: $html,
            textContent: "Merhaba {$user->name}, hesabinizi dogrulamak icin bu linki acin: {$verificationLink}"
        );
    }

    public function sendPasswordResetEmail(User $user, string $resetLink): void
    {
        $appName = (string) config('app.name', 'NextScout');
        $subject = "{$appName} sifre yenileme baglantisi";

        $html = '<html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:24px;">'
            .'<div style="max-width:600px;margin:0 auto;background:#fff;padding:32px;border-radius:8px;">'
            .'<h2 style="margin-top:0;color:#1e293b;">Sifre yenileme talebi</h2>'
            .'<p>Merhaba '.e((string) $user->name).',</p>'
            .'<p>Sifrenizi yenilemek icin asagidaki baglantiyi kullanin:</p>'
            .'<p><a href="'.e($resetLink).'" style="display:inline-block;background:#1a56db;color:#fff;text-decoration:none;padding:12px 20px;border-radius:6px;">Sifreyi yenile</a></p>'
            .'<p style="font-size:13px;color:#64748b;">Baglanti acilmazsa su adresi kopyalayin:<br>'.e($resetLink).'</p>'
            .'<p style="font-size:13px;color:#94a3b8;">Bu istegi siz yapmadiysaniz bu e-postayi yok sayabilirsiniz.</p>'
            .'</div></body></html>';

        $this->sendEmail(
            toEmail: (string) $user->email,
            toName: (string) $user->name,
            subject: $subject,
            htmlContent: $html,
            textContent: "Sifrenizi yenilemek icin bu linki acin: {$resetLink}"
        );
    }

    public function sendEmail(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent,
        string $textContent = ''
    ): void {
        if (! $this->isBrevoApiConfigured()) {
            $this->sendViaLaravelMailer($toEmail, $toName, $subject, $htmlContent, $textContent);

            return;
        }

        $payload = [
            'sender' => [
                'name' => $this->senderName,
                'email' => $this->senderEmail,
            ],
            'to' => [[
                'email' => $toEmail,
                'name' => $toName,
            ]],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ];

        if ($textContent !== '') {
            $payload['textContent'] = $textContent;
        }

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->timeout(15)->post($this->baseUrl.'/smtp/email', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Brevo mail send failed: '.$response->status().' '.$response->body());
        }
    }

    private function isBrevoApiConfigured(): bool
    {
        return $this->apiKey !== '' && $this->senderEmail !== '';
    }

    private function sendViaLaravelMailer(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent,
        string $textContent = ''
    ): void {
        Mail::send([], [], function ($message) use ($toEmail, $toName, $subject, $htmlContent, $textContent): void {
            $symfonyMessage = $message->getSymfonyMessage();

            if (! $symfonyMessage instanceof Email) {
                throw new RuntimeException('Symfony email message could not be created.');
            }

            $symfonyMessage
                ->subject($subject)
                ->from(new Address($this->senderEmail, $this->senderName))
                ->to(new Address($toEmail, $toName))
                ->html($htmlContent);

            if ($textContent !== '') {
                $symfonyMessage->text($textContent);
            }
        });
    }
}

<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('acesso.password_reset_frontend_url', 'http://localhost:3000'), '/');
        $email = method_exists($notifiable, 'getEmailForPasswordReset')
            ? $notifiable->getEmailForPasswordReset()
            : $notifiable->email;

        $url = $frontendUrl . '/resetar-senha?' . http_build_query([
            'token' => $this->token,
            'email' => $email,
        ]);

        return (new MailMessage)
            ->subject('Redefinir senha - Sierra Móveis')
            ->view('emails.password-reset', [
                'brandName' => 'Sierra Móveis',
                'logoUrl' => $frontendUrl . '/logo.png',
                'resetUrl' => $url,
                'expirationMinutes' => 60,
            ]);
    }
}

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
            ->subject('Redefinir senha')
            ->greeting('Olá!')
            ->line('Recebemos uma solicitação para redefinir a senha da sua conta.')
            ->action('Redefinir senha', $url)
            ->line('Este link expira em 60 minutos.')
            ->line('Se você não solicitou a redefinição, nenhuma ação é necessária.');
    }
}

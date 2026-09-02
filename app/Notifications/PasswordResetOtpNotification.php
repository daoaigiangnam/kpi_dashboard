<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $otp,
        private readonly bool $initialSetup = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        if ($this->initialSetup) {
            return (new MailMessage)
                ->subject('KPI Dashboard - Set Your Password')
                ->greeting('Hello '.$notifiable->name.',')
                ->line('Your KPI Dashboard account has been created.')
                ->line('Use the one-time OTP code below and the secure link to create your password.')
                ->line('Your one-time OTP code is: **'.$this->otp.'**')
                ->line('The OTP expires in 10 minutes and can only be used for this password setup request.')
                ->action('Set Password', $url)
                ->line('The secure link expires in 60 minutes.');
        }

        return (new MailMessage)
            ->subject('KPI Dashboard - Password Reset')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A password reset was requested for your KPI Dashboard account.')
            ->line('Your one-time OTP code is: **'.$this->otp.'**')
            ->line('The OTP expires in 10 minutes and can only be used for this password reset request.')
            ->action('Change Password', $url)
            ->line('The reset link expires in 60 minutes.')
            ->line('If you did not request a password reset, you can safely ignore this email.');
    }
}

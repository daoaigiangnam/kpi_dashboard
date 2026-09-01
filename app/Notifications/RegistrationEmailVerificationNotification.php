<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationEmailVerificationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $otp,
        private readonly int $expireMinutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('KPI Dashboard System - Verify Your Email')
            ->greeting('Hello,')
            ->line('You requested to create an account in KPI Dashboard System.')
            ->line('Your email verification OTP is: **'.$this->otp.'**')
            ->line('This OTP expires in '.$this->expireMinutes.' minutes and can only be used once.')
            ->line('If you did not request this registration, you can safely ignore this email.');
    }
}

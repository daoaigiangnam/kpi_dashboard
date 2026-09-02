<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRegistrationNotification extends Notification
{
    public function __construct(private readonly User $user) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('KPI Dashboard System - Registration Pending Approval')
            ->greeting('New registration request')
            ->line('A new user registration is waiting for Super Admin review.')
            ->line('Employee Code: '.$this->user->employee_code)
            ->line('Full Name: '.$this->user->name)
            ->line('Email: '.$this->user->email)
            ->line('Phone: '.($this->user->phone ?: '—'))
            ->line('Department: '.($this->user->departmentRelation?->name ?? '—'))
            ->line('Unit: '.($this->user->unit?->name ?? '—'))
            ->line('Job Title: '.($this->user->jobTitle?->name ?? '—'))
            ->line('Submitted at: '.now()->format('Y-m-d H:i:s'))
            ->action('Review Registration', route('admin.users.pending'))
            ->line('Please approve or reject this registration from the Admin area.');
    }
}

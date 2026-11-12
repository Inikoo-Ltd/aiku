<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-09h-08m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Notifications;

use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MobilePasswordResetCodeNotification extends Notification
{
    use SendsMailThroughSes;

    public function __construct(public string $code, public int $minutes)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject(__('Your Aiku password reset code'))
            ->line(__('You are receiving this email because we received a password reset request for your account.'))
            ->line(__('Your code is :code', ['code' => $this->code]))
            ->line(__('This code will expire in :count minutes.', ['count' => $this->minutes]))
            ->line(__('If you did not request a password reset, no further action is required.'));
    }
}

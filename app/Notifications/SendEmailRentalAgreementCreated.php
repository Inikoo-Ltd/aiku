<?php

namespace App\Notifications;

use App\Channel\CustomMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendEmailRentalAgreementCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['mail'];
    }


    public function toMail($notifiable): MailMessage
    {
        $message = (new CustomMailMessage($notifiable))
                    ->line("Here is your credentials to login to {$notifiable->shop->name}.")
                    ->line("Username: $notifiable->username")
                    ->line("Use Forgot password on the login page to set your password.")
                    ->action('Log in', $notifiable->shop->website->domain.'/app/login')
                    ->line("Thank you for using {$notifiable->shop->name}.");

        if (app()->isProduction()) {
            $message->mailer('ses')->from($notifiable->shop->email ?: 'help@aiku.io', $notifiable->shop->name);
        }

        return $message;
    }

    public function toArray($notifiable): array
    {
        return [
            //
        ];
    }
}

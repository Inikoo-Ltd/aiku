<?php

namespace App\Notifications;

use App\Channel\CustomMailMessage;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail(WebUser|User $notifiable): MailMessage
    {
        /** @var Outbox $outbox */
        $outbox = $notifiable->shop?->outboxes()->where('type', OutboxCodeEnum::PASSWORD_REMINDER->value)
            ->first();

        if (! $outbox) {
            throw ValidationException::withMessages(['outbox' => 'No outboxes in this shop']);
        }

        $data = $outbox->emailTemplate->published_layout;

        $message = (new CustomMailMessage($notifiable))
            ->subject(Lang::get(Arr::get($data, 'subject', 'Reset Password Notification')))
            ->line(Lang::get(Arr::get($data, 'header', 'You are receiving this email because we received a password reset request for your account.')))
            ->action(Lang::get(Arr::get($data, 'action', 'Reset Password')), $this->url)
            ->line(Lang::get(Arr::get($data, 'footer', 'This password reset link will expire in :count minutes.'), ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
            ->line(Lang::get(Arr::get($data, 'notes', 'If you did not request a password reset, no further action is required.')));

        if (app()->isProduction()) {
            if ($notifiable instanceof WebUser) {
                $message->from($notifiable->shop->email);
            } else {
                $message->from($notifiable->group->email);
            }
        }

        return $message;

    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}

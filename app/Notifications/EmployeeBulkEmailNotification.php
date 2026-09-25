<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class EmployeeBulkEmailNotification extends Notification
{
    use Queueable;

    /**
     * @param array<int, array{path: string, name: string}> $attachments
     */
    public function __construct(public string $subject, public string $body, public string $recipientName, public string $organisationName, public array $attachments = [], public ?string $replyToEmail = null)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject($this->subject)
            ->markdown('notifications::email', ['shop' => $this->organisationName, 'shop_url' => config('app.url')])
            ->greeting(__('Hello :name,', ['name' => $this->recipientName]))
            ->line(new HtmlString($this->body));

        if (app()->isProduction()) {
            $message->mailer('ses')->from('help@aiku.io', $this->organisationName);
        }

        if ($this->replyToEmail) {
            $message->replyTo($this->replyToEmail);
        }

        foreach ($this->attachments as $attachment) {
            $message->attach(Attachment::fromStorageDisk('local', $attachment['path'])->as($attachment['name']));
        }

        return $message;
    }
}

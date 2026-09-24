<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForwardedChatSessionNotification extends Notification
{
    public function __construct(
        public string $title,
        public ?string $forwardedBy,
        public string $note,
        public string $transcript,
        public ?string $url,
        public ?string $replyTo,
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject(__('Forwarded: :title', ['title' => $this->title]))
            ->line(__(':name forwarded this conversation to you.', ['name' => $this->forwardedBy ?? __('A colleague')]));

        if ($this->note !== '') {
            $mail->line($this->note);
        }

        if ($this->url) {
            $mail->action(__('Open in Aiku'), $this->url);
        }

        // The mail is a copy, never the place to answer from: a reply typed here would reach the
        // colleague who forwarded it and leave no trace on the customer's conversation.
        $mail->line(__('Answer the customer in Aiku, not by replying to this mail.'));

        foreach (explode("\n\n", $this->transcript) as $entry) {
            if (trim($entry) !== '') {
                $mail->line($entry);
            }
        }

        if ($this->replyTo) {
            $mail->replyTo($this->replyTo);
        }

        return $mail;
    }
}

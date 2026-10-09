<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The weekly SEO report by email, of one shop or of every website.
 */
class SeoWeeklyReportNotification extends Notification
{
    /**
     * @param  array<int, array{title: string, lines: array<int, string|null>}>  $sections
     * @param  array{name: string, parameters: array}  $route
     */
    public function __construct(public string $subject, public string $scope, public array $sections, public array $route)
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
            ->markdown('notifications::email', ['shop' => $this->scope, 'shop_url' => config('app.url')])
            ->greeting(__('Hello :name,', ['name' => $notifiable->contact_name ?: $notifiable->username]));

        if (app()->isProduction()) {
            $message->mailer('ses')->from('help@aiku.io', 'Aiku');
        }

        if ($this->sections === []) {
            $message->line(__('Nothing was recorded for this week yet.'));
        }

        foreach ($this->sections as $section) {
            $message->line('**'.$section['title'].'**');

            foreach (array_filter($section['lines']) as $line) {
                $message->line($line);
            }
        }

        return $message
            ->action(__('Open in Aiku'), route($this->route['name'], $this->route['parameters']))
            ->line(__('You get this email because you turned on the weekly SEO report in Aiku. Turn it off there.'));
    }
}

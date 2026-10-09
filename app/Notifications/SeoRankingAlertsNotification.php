<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Notifications;

use App\Models\Catalogue\Shop;
use App\Models\Web\SeoRankingAlert;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * One message per person and shop after a round of Google checks, listing the watched keywords that
 * fell, in the bell and by email.
 */
class SeoRankingAlertsNotification extends Notification
{
    private const int LINES_SHOWN = 20;

    /**
     * @param  Collection<int, SeoRankingAlert>  $alerts
     */
    public function __construct(public Shop $shop, public Collection $alerts)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->subject(),
            'body'  => $this->lines()->take(3)->implode("\n"),
            'type'  => 'seo_ranking_alerts',
            'slug'  => $this->shop->slug,
            'route' => $this->route(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject($this->subject())
            ->markdown('notifications::email', ['shop' => $this->shop->name, 'shop_url' => config('app.url')])
            ->greeting(__('Hello :name,', ['name' => $notifiable->contact_name ?: $notifiable->username]))
            ->line(__('Keywords you watch fell in the latest Google check of :shop:', ['shop' => $this->shop->name]));

        if (app()->isProduction()) {
            $message->mailer('ses')->from('help@aiku.io', 'Aiku');
        }

        $lines = $this->lines();

        foreach ($lines->take(self::LINES_SHOWN) as $line) {
            $message->line('- '.$line);
        }

        if ($lines->count() > self::LINES_SHOWN) {
            $message->line(__('And :count more.', ['count' => $lines->count() - self::LINES_SHOWN]));
        }

        return $message->action(__('Open the rankings'), route($this->route()['name'], $this->route()['parameters']));
    }

    private function subject(): string
    {
        return trans_choice('{1} 1 watched keyword fell · :shop|[2,*] :count watched keywords fell · :shop', $this->alerts->count(), ['shop' => $this->shop->name]);
    }

    /**
     * @return Collection<int, string>
     */
    private function lines(): Collection
    {
        return $this->alerts->map(fn (SeoRankingAlert $alert) => match ($alert->reason) {
            SeoRankingAlert::REASON_LEFT_TOP_10 => __(':keyword left the top 10: :previous to :position', ['keyword' => $alert->trackedKeyword->keyword, 'previous' => $alert->previous_position, 'position' => $alert->position ?? __('not in the results read')]),
            SeoRankingAlert::REASON_LOST        => __(':keyword is no longer in the results read (was :previous)', ['keyword' => $alert->trackedKeyword->keyword, 'previous' => $alert->previous_position]),
            default                             => __(':keyword dropped from :previous to :position', ['keyword' => $alert->trackedKeyword->keyword, 'previous' => $alert->previous_position, 'position' => $alert->position]),
        });
    }

    /**
     * @return array{name: string, parameters: array}
     */
    private function route(): array
    {
        return [
            'name'       => 'grp.org.shops.show.seo.keywords.show',
            'parameters' => [$this->shop->organisation->slug, $this->shop->slug, 'tab' => 'rankings'],
        ];
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class ShoppingAssistantWeeklySummaryNotification extends Notification
{
    use SendsMailThroughSes;

    /**
     * @param  array<string, mixed>  $summary  SendShoppingAssistantWeeklySummary::summary()
     */
    public function __construct(public array $summary)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function title(): string
    {
        return __('AI shopping assistant, week of :from to :to', ['from' => $this->summary['from'], 'to' => $this->summary['to']]);
    }

    public function toMail($notifiable): MailMessage
    {
        $kpis  = $this->summary['kpis'];
        $money = fn (float $amount, string $currency) => Number::currency($amount, $currency);
        $was   = fn (array $metric, ?callable $format = null) => __(':value (week before :previous)', [
            'value'    => $format ? $format((float) $metric['value']) : Number::format((float) $metric['value']),
            'previous' => $format ? $format((float) $metric['previous']) : Number::format((float) $metric['previous']),
        ]);

        $mail = $this->newMailMessage()
            ->subject($this->title())
            ->line(__('Conversations: :value', ['value' => $was($kpis['conversations'])]))
            ->line(__('Customers: :value', ['value' => $was($kpis['customers'])]))
            ->line(__('Orders with products the assistant showed: :value', ['value' => $was($kpis['orders'])]))
            ->line(__('Revenue of those lines: :value', ['value' => $was($kpis['revenue'], fn (float $amount) => $money($amount, $this->summary['currency']))]))
            ->line(__('Added to the basket straight from the assistant: :value', ['value' => $was($kpis['strict_revenue'], fn (float $amount) => $money($amount, $this->summary['currency']))]))
            ->line(__('AI cost: :value', ['value' => $was($kpis['ai_cost'], fn (float $amount) => $money($amount, $this->summary['cost_currency']))]))
            ->line(__('Answers rated: :up helpful, :down not helpful', ['up' => $this->summary['feedback']['up'], 'down' => $this->summary['feedback']['down']]))
            ->line(__('Passed to our team: :value', ['value' => $was($this->summary['hand_overs'])]));

        if ($this->summary['challenges']) {
            $mail->line('**'.__('Top customer challenges').'**');
            foreach ($this->summary['challenges'] as $challenge) {
                $mail->line(__(':label: :topic (:count)', ['label' => $challenge['label'], 'topic' => $this->plain($challenge['topic']), 'count' => trans_choice(':count conversation|:count conversations', $challenge['conversations'])]));
            }
        }

        if ($this->summary['zero_searches']) {
            $mail->line('**'.__('Searches with no results').'**');
            foreach ($this->summary['zero_searches'] as $search) {
                $mail->line(__(':query (:count)', ['query' => $this->plain($search['query']), 'count' => $search['searches']]));
            }
        }

        return $mail->action(__('Open the report'), $this->summary['url']);
    }

    /**
     * Search words typed by visitors and topics written by AI, as plain text: mail lines are
     * Markdown, so a query like [win](https://...) would otherwise reach the inbox as a link.
     */
    public function plain(string $text): string
    {
        $text = Str::limit(trim(preg_replace('/\s+/u', ' ', $text)), 80);
        $text = str_replace(['://', 'www.'], [":\u{200B}//", "www\u{200B}."], $text);

        return preg_replace('/([\\\\`*_{}\[\]()<>#!|~+\-.])/u', '\\\\$1', $text);
    }
}

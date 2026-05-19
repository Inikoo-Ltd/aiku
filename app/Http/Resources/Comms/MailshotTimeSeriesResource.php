<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 May 2026 11:22:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Comms;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class MailshotTimeSeriesResource extends JsonResource
{
    public function toArray($request): array
    {
        $frequency = request()->input('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        $frequencyEnum = TimeSeriesFrequencyEnum::tryFrom($frequency) ?? TimeSeriesFrequencyEnum::DAILY;

        return [
            'id' => $this->id,
            'period' => $this->formatPeriod($this->from, $this->to, $frequencyEnum),
            'filter_date' => $this->formatFilterDate($this->from, $this->to),
            'error' => (int) $this->number_dispatched_emails_state_error,
            'sent' => (int) $this->number_dispatched_emails_state_sent,
            'delivered' => (int) $this->number_dispatched_emails_state_delivered,
            'hard_bounce' => (int) $this->number_dispatched_emails_state_hard_bounce,
            'soft_bounce' => (int) $this->number_dispatched_emails_state_soft_bounce,
            'opened' => (int) $this->number_dispatched_emails_state_opened,
            'clicked' => (int) $this->number_dispatched_emails_state_clicked,
            'spam' => (int) $this->number_dispatched_emails_state_spam,
            'unsubscribed' => (int) $this->number_dispatched_emails_state_unsubscribed,
            'delay' => (int) $this->number_dispatched_emails_state_sent_to_provider,
            'open_rate' => (float) $this->openRate(),
            'clicked_rate' => (float) $this->clickedRate(),
            'spam_rate' => (float) $this->spamRate(),
            'unsubscribe_rate' => (float) $this->unsubscribeRate(),
        ];
    }

    protected function formatPeriod(?Carbon $from, ?Carbon $to, TimeSeriesFrequencyEnum $frequency): string
    {
        if (!$from) {
            return '-';
        }

        return match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY => $from->format('d M Y'),
            TimeSeriesFrequencyEnum::WEEKLY => $from->format('d M') . ' - ' . ($to ? $to->format('d M Y') : ''),
            TimeSeriesFrequencyEnum::MONTHLY => $from->format('M Y'),
            TimeSeriesFrequencyEnum::QUARTERLY => 'Q' . $from->quarter . ' ' . $from->format('Y'),
            TimeSeriesFrequencyEnum::YEARLY => $from->format('Y'),
        };
    }

    protected function formatFilterDate(?Carbon $from, ?Carbon $to): string
    {
        if (!$from || !$to) {
            return '-';
        }

        return $from->format('Ymd') . '-' . $to->format('Ymd');
    }
}

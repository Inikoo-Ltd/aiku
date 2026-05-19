<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 May 2026 11:22:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mailshot_time_series_id
 * @property string $frequency
 * @property int $number_dispatched_emails
 * @property int $number_dispatched_emails_state_ready
 * @property int $number_dispatched_emails_state_sent_to_provider
 * @property int $number_dispatched_emails_state_error
 * @property int $number_dispatched_emails_state_rejected_by_provider
 * @property int $number_dispatched_emails_state_sent
 * @property int $number_dispatched_emails_state_delivered
 * @property int $number_dispatched_emails_state_hard_bounce
 * @property int $number_dispatched_emails_state_soft_bounce
 * @property int $number_dispatched_emails_state_opened
 * @property int $number_dispatched_emails_state_clicked
 * @property int $number_dispatched_emails_state_spam
 * @property int $number_dispatched_emails_state_unsubscribed
 * @property int $number_provoked_unsubscribe
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string|null $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Comms\MailshotTimeSeries|null $mailshotTimeSeries
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MailshotTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MailshotTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MailshotTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class MailshotTimeSeriesRecord extends Model
{
    protected $table = 'mailshot_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to' => 'datetime',
        ];
    }

    public function mailshotTimeSeries(): BelongsTo
    {
        return $this->belongsTo(MailshotTimeSeries::class);
    }

    public function openRate(): float
    {
        return $this->number_dispatched_emails > 0
            ? round($this->number_dispatched_emails_state_opened / $this->number_dispatched_emails * 100, 2)
            : 0;
    }

    public function clickedRate(): float
    {
        return $this->number_dispatched_emails > 0
            ? round($this->number_dispatched_emails_state_clicked / $this->number_dispatched_emails * 100, 2)
            : 0;
    }

    public function spamRate(): float
    {
        return $this->number_dispatched_emails > 0
            ? round($this->number_dispatched_emails_state_spam / $this->number_dispatched_emails * 100, 2)
            : 0;
    }

    public function unsubscribeRate(): float
    {
        return $this->number_dispatched_emails > 0
            ? round($this->number_provoked_unsubscribe / $this->number_dispatched_emails * 100, 2)
            : 0;
    }
}

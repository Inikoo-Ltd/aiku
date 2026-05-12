<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 May 2026 11:22:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Models\Comms;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $mailshot_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Comms\Mailshot|null $mailshot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comms\MailshotTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MailshotTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MailshotTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MailshotTimeSeries query()
 * @mixin \Eloquent
 */
class MailshotTimeSeries extends Model
{
    protected $table = 'mailshot_time_series';

    protected $guarded = [];

    protected $casts = [
        'data'      => 'array',
        'frequency' => TimeSeriesFrequencyEnum::class,
        'from'      => 'date',
        'to'        => 'date',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function mailshot(): BelongsTo
    {
        return $this->belongsTo(Mailshot::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(MailshotTimeSeriesRecord::class);
    }
}

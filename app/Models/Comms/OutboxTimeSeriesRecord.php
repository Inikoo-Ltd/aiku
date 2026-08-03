<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 00:55:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $outbox_time_series_id
 * @property string $frequency
 * @property int|null $runs
 * @property int|null $dispatched_emails
 * @property int|null $opened_emails
 * @property int|null $clicked_emails
 * @property int|null $bounced_emails
 * @property int|null $subscribed
 * @property int|null $unsubscribed
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string|null $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class OutboxTimeSeriesRecord extends Model
{
    protected $table = 'outbox_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to'   => 'datetime',
        ];
    }
}

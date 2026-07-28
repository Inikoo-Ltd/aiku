<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 15:46:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_time_series_id
 * @property string $frequency
 * @property string $period
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property int $number_requests
 * @property int $number_logins
 * @property int $number_active_days
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class UserTimeSeriesRecord extends Model
{
    protected $table = 'user_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to'   => 'datetime',
        ];
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 15:54:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $webpage_time_series_id
 * @property string $frequency
 * @property int|null $visitors
 * @property int|null $page_views
 * @property int|null $add_to_baskets
 * @property numeric|null $conversion_rate
 * @property int|null $avg_time_on_page
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string|null $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebpageTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebpageTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebpageTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class WebpageTimeSeriesRecord extends Model
{
    protected $table = 'webpage_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from'            => 'datetime',
            'to'              => 'datetime',
            'conversion_rate' => 'decimal:2',
        ];
    }
}

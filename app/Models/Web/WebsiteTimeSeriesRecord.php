<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 15:53:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $website_time_series_id
 * @property string $frequency
 * @property int|null $visitors
 * @property int|null $sessions
 * @property int|null $page_views
 * @property int|null $avg_session_duration
 * @property numeric|null $bounce_rate
 * @property numeric|null $pages_per_session
 * @property int|null $new_visitors
 * @property int|null $returning_visitors
 * @property int|null $visitors_desktop
 * @property int|null $visitors_mobile
 * @property int|null $visitors_tablet
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string|null $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class WebsiteTimeSeriesRecord extends Model
{
    protected $table = 'website_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from'              => 'datetime',
            'to'                => 'datetime',
            'bounce_rate'       => 'decimal:2',
            'pages_per_session' => 'decimal:2',
        ];
    }
}

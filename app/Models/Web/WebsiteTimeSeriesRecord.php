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
 * @property int $visitors
 * @property int $sessions
 * @property int $page_views
 * @property int $avg_session_duration
 * @property string $bounce_rate
 * @property string $pages_per_session
 * @property int $new_visitors
 * @property int $returning_visitors
 * @property int $visitors_desktop
 * @property int $visitors_mobile
 * @property int $visitors_tablet
 * @property string|null $from
 * @property string|null $to
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



}

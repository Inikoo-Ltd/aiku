<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Aug 2023 16:16:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $banner_id
 * @property int $number_snapshots
 * @property int $number_snapshots_state_unpublished
 * @property int $number_snapshots_state_live
 * @property int $number_snapshots_state_historic
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Web\Banner $banner
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BannerStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BannerStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BannerStats query()
 * @mixin \Eloquent
 */
class BannerStats extends Model
{
    protected $table = 'banner_stats';

    protected $guarded = [];

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Banner::class);
    }
}

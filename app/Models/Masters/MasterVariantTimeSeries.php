<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Dec 2025 21:29:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $master_variant_id
 * @property string $frequency
 * @property string|null $from
 * @property string|null $to
 * @property string|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Masters\MasterVariant $masterVariant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariantTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariantTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariantTimeSeries query()
 * @mixin \Eloquent
 */
class MasterVariantTimeSeries extends Model
{
    protected $guarded = [];

    public function masterVariant(): BelongsTo
    {
        return $this->belongsTo(MasterVariant::class);
    }
}

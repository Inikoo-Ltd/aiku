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
 * @property-read \App\Models\Masters\MasterVariant|null $masterVariant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariantOrderingIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariantOrderingIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariantOrderingIntervals query()
 * @mixin \Eloquent
 */
class MasterVariantOrderingIntervals extends Model
{
    protected $guarded = [];

    public function masterVariant(): BelongsTo
    {
        return $this->belongsTo(MasterVariant::class);
    }
}

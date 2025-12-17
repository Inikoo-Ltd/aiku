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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Masters\MasterVariant $masterVariant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariantStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariantStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterVariantStats query()
 * @mixin \Eloquent
 */
class MasterVariantStats extends Model
{
    protected $table = 'master_variant_stats';

    protected $guarded = [];

    public function masterVariant(): BelongsTo
    {
        return $this->belongsTo(MasterVariant::class);
    }
}

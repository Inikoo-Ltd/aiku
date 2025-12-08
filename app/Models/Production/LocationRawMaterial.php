<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 19:13:08 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Models\Inventory\Location;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * App\Models\Inventory\LocationRawMaterial
 *
 * @property RawMaterialTypeEnum $type
 * @property-read Location|null $location
 * @property-read \App\Models\Production\RawMaterial|null $rawMaterial
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocationRawMaterial newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocationRawMaterial newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocationRawMaterial query()
 *
 * @mixin \Eloquent
 */
class LocationRawMaterial extends Pivot
{
    public $incrementing = true;

    protected $casts = [
        'data' => 'array',
        'settings' => 'array',
        'type' => RawMaterialTypeEnum::class,
    ];

    protected $attributes = [
        'data' => '{}',
        'settings' => '{}',
    ];

    protected $guarded = [];

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}

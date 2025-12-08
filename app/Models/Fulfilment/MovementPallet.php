<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 30 Jan 2024 16:47:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\MovementPallet
 *
 * @property int $id
 * @property int $pallet_id
 * @property int|null $location_from_id
 * @property int|null $location_to_id
 * @property \Illuminate\Support\Carbon $moved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MovementPallet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MovementPallet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MovementPallet query()
 *
 * @mixin \Eloquent
 */
class MovementPallet extends Model
{
    protected $guarded = [];

    protected $casts = [
        'moved_at' => 'datetime',
    ];
}

<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 13 Mar 2024 09:43:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\StoredItemMovement\StoredItemMovementTypeEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Fulfilment\StoredItemMovement
 *
 * @property int $id
 * @property int $stored_item_id
 * @property int|null $location_id
 * @property StoredItemMovementTypeEnum $type
 * @property string $quantity
 * @property string $moved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $pallet_id
 * @property int|null $stored_item_audit_id
 * @property int|null $stored_item_audit_delta_id
 * @property int|null $pallet_delivery_id
 * @property int|null $pallet_return_id
 * @property int|null $pallet_return_item_id
 * @property string|null $running_quantity
 * @property string|null $running_in_pallet_quantity
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoredItemMovement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoredItemMovement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoredItemMovement query()
 *
 * @mixin \Eloquent
 */
class StoredItemMovement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'type' => StoredItemMovementTypeEnum::class,
    ];
}

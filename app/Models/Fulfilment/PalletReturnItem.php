<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Apr 2024 14:12:20 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Models\Inventory\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\PalletReturnItem
 *
 * @property int $id
 * @property string $type Pallet|StoredItem
 * @property int $pallet_return_id
 * @property int $pallet_id
 * @property int|null $stored_item_id
 * @property int|null $pallet_stored_item_id
 * @property string $quantity_ordered
 * @property string $quantity_dispatched
 * @property string $quantity_fail
 * @property string $quantity_cancelled
 * @property int|null $picking_location_id
 * @property PalletReturnItemStateEnum $state
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $quantity_picked
 * @property string|null $not_setup_reason
 * @property-read \App\Models\Fulfilment\Pallet $pallet
 * @property-read \App\Models\Fulfilment\PalletReturn $palletReturn
 * @property-read \App\Models\Fulfilment\PalletStoredItem|null $palletStoredItem
 * @property-read Location|null $pickingLocation
 * @property-read \App\Models\Fulfilment\StoredItem|null $storedItem
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletReturnItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletReturnItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletReturnItem query()
 *
 * @mixin \Eloquent
 */
class PalletReturnItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'state' => PalletReturnItemStateEnum::class,
        'in_process_at' => 'datetime',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'picking_at' => 'datetime',
        'picked_at' => 'datetime',
        'not_picked_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'cancel_at' => 'datetime',
    ];

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function palletReturn(): BelongsTo
    {
        return $this->belongsTo(PalletReturn::class);
    }

    public function palletStoredItem(): BelongsTo
    {
        return $this->belongsTo(PalletStoredItem::class);
    }

    public function storedItem(): BelongsTo
    {
        return $this->belongsTo(StoredItem::class);
    }

    public function pickingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'picking_location_id');
    }
}

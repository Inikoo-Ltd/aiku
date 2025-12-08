<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 25 Jan 2024 11:03:38 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Fulfilment\PalletStoredItem
 *
 * @property int $id
 * @property int $pallet_id
 * @property int $stored_item_id
 * @property string $quantity
 * @property string $damaged_quantity
 * @property string $source
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_audits
 * @property string|null $last_audit_at
 * @property int|null $last_stored_item_audit_delta_id
 * @property int|null $last_stored_item_audit_id
 * @property bool $in_process
 * @property PalletStoredItemStateEnum $state
 * @property string|null $delivered_quantity
 * @property-read \App\Models\Fulfilment\Pallet $pallet
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\PalletReturnItem> $palletReturnItems
 * @property-read \App\Models\Fulfilment\StoredItem $storedItem
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletStoredItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletStoredItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletStoredItem query()
 *
 * @mixin \Eloquent
 */
class PalletStoredItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'state' => PalletStoredItemStateEnum::class,
    ];

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function storedItem(): BelongsTo
    {
        return $this->belongsTo(StoredItem::class);
    }

    public function palletReturnItems(): HasMany
    {
        return $this->hasMany(PalletReturnItem::class);
    }
}

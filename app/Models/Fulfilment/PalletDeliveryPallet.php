<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 30 Jan 2024 16:47:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * App\Models\Fulfilment\PalletDeliveryPallet
 *
 * @property-read \App\Models\Fulfilment\Pallet|null $pallet
 * @property-read \App\Models\Fulfilment\PalletDelivery|null $palletDelivery
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletDeliveryPallet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletDeliveryPallet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletDeliveryPallet query()
 *
 * @mixin \Eloquent
 */
class PalletDeliveryPallet extends Pivot
{
    protected $guarded = [];

    public function palletDelivery(): BelongsTo
    {
        return $this->belongsTo(PalletDelivery::class);
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }
}

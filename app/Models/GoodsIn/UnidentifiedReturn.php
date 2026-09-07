<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Models\Traits\HasImage;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A box at goods-in whose delivery note could not be found: a photo and whatever was written on
 * the box, waiting for the office to identify it. delivery_note_id null = still unidentified.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $warehouse_id
 * @property string|null $notes
 * @property int|null $image_id
 * @property int|null $delivery_note_id
 * @property int|null $return_delivery_note_id
 * @property \Illuminate\Support\Carbon|null $identified_at
 *
 * @mixin \Eloquent
 */
class UnidentifiedReturn extends Model
{
    use SoftDeletes;
    use InOrganisation;
    use HasImage;

    protected $table = 'unidentified_returns';

    protected $guarded = [];

    protected $casts = [
        'identified_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function returnDeliveryNote(): BelongsTo
    {
        return $this->belongsTo(ReturnDeliveryNote::class);
    }
}

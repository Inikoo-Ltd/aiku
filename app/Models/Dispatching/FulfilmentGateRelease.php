<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 30 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Models\Ordering\Order;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Dispatching\FulfilmentGateRelease
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $warehouse_id
 * @property int|null $customer_id
 * @property int $order_id
 * @property int|null $delivery_note_id
 * @property numeric $net_amount
 * @property int $number_items
 * @property int|null $seconds_since_last_release
 * @property int|null $released_by_user_id
 */
class FulfilmentGateRelease extends Model
{
    use InOrganisation;

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 22:27:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Dispatching\DeliveryNoteStats
 *
 * @property int $id
 * @property int $delivery_note_id
 * @property int $number_items current number of items
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_waiting_items current number of waiting items
 * @property int $number_waiting_items_type_production
 * @property int $number_waiting_items_type_restock
 * @property int $number_waiting_items_type_customer
 * @property int $number_waiting_items_state_to_do
 * @property int $number_waiting_items_state_escalated
 * @property int $number_waiting_items_state_in_progress
 * @property int $number_waiting_items_state_done
 * @property int $number_waiting_items_state_cancelled
 * @property int $number_waiting_items_status_to_do
 * @property int $number_waiting_items_status_in_progress
 * @property int $number_waiting_items_status_done
 * @property int $number_waiting_items_type_production_state_to_do
 * @property int $number_waiting_items_type_production_state_escalated
 * @property int $number_waiting_items_type_production_state_in_progress
 * @property int $number_waiting_items_type_production_state_done
 * @property int $number_waiting_items_type_production_state_cancelled
 * @property int $number_waiting_items_type_production_status_to_do
 * @property int $number_waiting_items_type_production_status_in_progress
 * @property int $number_waiting_items_type_production_status_done
 * @property int $number_waiting_items_type_restock_state_to_do
 * @property int $number_waiting_items_type_restock_state_escalated
 * @property int $number_waiting_items_type_restock_state_in_progress
 * @property int $number_waiting_items_type_restock_state_done
 * @property int $number_waiting_items_type_restock_state_cancelled
 * @property int $number_waiting_items_type_restock_status_to_do
 * @property int $number_waiting_items_type_restock_status_in_progress
 * @property int $number_waiting_items_type_restock_status_done
 * @property int $number_waiting_items_type_customer_state_to_do
 * @property int $number_waiting_items_type_customer_state_escalated
 * @property int $number_waiting_items_type_customer_state_in_progress
 * @property int $number_waiting_items_type_customer_state_done
 * @property int $number_waiting_items_type_customer_state_cancelled
 * @property int $number_waiting_items_type_customer_status_to_do
 * @property int $number_waiting_items_type_customer_status_in_progress
 * @property int $number_waiting_items_type_customer_status_done
 * @property-read \App\Models\Dispatching\DeliveryNote $deliveryNote
 * @method static Builder<static>|DeliveryNoteStats newModelQuery()
 * @method static Builder<static>|DeliveryNoteStats newQuery()
 * @method static Builder<static>|DeliveryNoteStats query()
 * @mixin Eloquent
 */
class DeliveryNoteStats extends Model
{
    protected $table = 'delivery_note_stats';

    protected $guarded = [];

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }
}

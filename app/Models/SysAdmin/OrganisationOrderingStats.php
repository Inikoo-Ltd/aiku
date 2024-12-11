<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 19:12:01 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $organisation_id
 * @property string|null $last_order_created_at
 * @property string|null $last_order_submitted_at
 * @property string|null $last_order_dispatched_at
 * @property int $number_orders
 * @property int $number_orders_state_creating
 * @property int $number_orders_state_submitted
 * @property int $number_orders_state_in_warehouse
 * @property int $number_orders_state_handling
 * @property int $number_orders_state_handling_blocked
 * @property int $number_orders_state_packed
 * @property int $number_orders_state_finalised
 * @property int $number_orders_state_dispatched
 * @property int $number_orders_state_cancelled
 * @property int $number_orders_status_creating
 * @property int $number_orders_status_processing
 * @property int $number_orders_status_settled
 * @property int $number_orders_handing_type_collection
 * @property int $number_orders_handing_type_shipping
 * @property int $number_invoices
 * @property int $number_invoices_type_invoice
 * @property int $number_invoices_type_refund
 * @property string|null $last_invoiced_at
 * @property int $number_invoiced_customers
 * @property string|null $last_delivery_note_created_at
 * @property string|null $last_delivery_note_dispatched_at
 * @property string|null $last_delivery_note_type_order_created_at
 * @property string|null $last_delivery_note_type_order_dispatched_at
 * @property string|null $last_delivery_note_type_replacement_created_at
 * @property string|null $last_delivery_note_type_replacement_dispatched_at
 * @property int $number_delivery_notes
 * @property int $number_delivery_notes_type_order
 * @property int $number_delivery_notes_type_replacement
 * @property int $number_delivery_notes_state_unassigned
 * @property int $number_delivery_notes_state_queued
 * @property int $number_delivery_notes_state_handling
 * @property int $number_delivery_notes_state_handling_blocked
 * @property int $number_delivery_notes_state_packed
 * @property int $number_delivery_notes_state_finalised
 * @property int $number_delivery_notes_state_dispatched
 * @property int $number_delivery_notes_state_cancelled
 * @property int $number_delivery_notes_cancelled_at_state_unassigned
 * @property int $number_delivery_notes_cancelled_at_state_queued
 * @property int $number_delivery_notes_cancelled_at_state_handling
 * @property int $number_delivery_notes_cancelled_at_state_handling_blocked
 * @property int $number_delivery_notes_cancelled_at_state_packed
 * @property int $number_delivery_notes_cancelled_at_state_finalised
 * @property int $number_delivery_notes_cancelled_at_state_dispatched
 * @property int $number_delivery_notes_state_with_out_of_stock
 * @property int $number_purges
 * @property int $number_purges_state_in_process
 * @property int $number_purges_state_purging
 * @property int $number_purges_state_finished
 * @property int $number_purges_state_cancelled
 * @property int $number_purges_type_cron
 * @property int $number_purges_type_manual
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationOrderingStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationOrderingStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationOrderingStats query()
 * @mixin \Eloquent
 */
class OrganisationOrderingStats extends Model
{
    protected $table = 'organisation_ordering_stats';

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}

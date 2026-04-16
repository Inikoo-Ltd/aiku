<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 16:14:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexDeliveryNoteItemsCrm extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, $prefix = null): LengthAwarePaginator
    {


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DeliveryNoteItem::class);

        $query->where('delivery_note_items.delivery_note_id', $deliveryNote->id);
        $query->where('delivery_note_items.has_waiting_crm','true');
        $query->leftjoin('org_stocks', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id');

        return $query
            ->defaultSort( 'org_stocks.code')
            ->select([
                'delivery_note_items.id',
                'delivery_note_items.state',
                'delivery_note_items.quantity_required',
                'delivery_note_items.quantity_picked',
                'delivery_note_items.quantity_not_picked',
                'delivery_note_items.quantity_packed',
                'delivery_note_items.quantity_dispatched',
                'delivery_note_items.quantity_waiting_warehouse',
                'delivery_note_items.quantity_waiting_crm',
                'delivery_note_items.is_handled',
                'delivery_note_items.batch_code',
                'delivery_note_items.expiry_date',
                'delivery_note_items.notes',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.packed_in'
            ])
            ->allowedSorts(['id', 'org_stock_name', 'org_stock_code', 'quantity_required', 'quantity_picked', 'quantity_packed', 'state', 'picking_position'])
            ->withPaginator( 'deliveryNoteItems', tableName: request()->route()->getName())
            ->withQueryString();
    }




}

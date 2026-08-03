<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 16:14:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\Dispatching\DeliveryNoteItem\UI\Traits\WithDeliveryNoteItemUI;
use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexDeliveryNoteItemsCrm extends OrgAction
{
    use WithDeliveryNoteItemUI;

    public function handle(DeliveryNote $deliveryNote, $prefix = null): LengthAwarePaginator
    {


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DeliveryNoteItem::class);

        $query->where('delivery_note_items.delivery_note_id', $deliveryNote->id);
        $query->where('delivery_note_items.has_waiting_crm', 'true');
        $this->applyDeliveryNoteItemBaseJoins($query);

        return $query
            ->defaultSort('org_stocks.code')
            ->select($this->getDeliveryNoteItemBaseSelect())
            ->allowedSorts(array_merge($this->getDeliveryNoteItemBaseSorts(), ['picking_position']))
            ->withPaginator('deliveryNoteItems', tableName: request()->route()->getName())
            ->withQueryString();
    }




}

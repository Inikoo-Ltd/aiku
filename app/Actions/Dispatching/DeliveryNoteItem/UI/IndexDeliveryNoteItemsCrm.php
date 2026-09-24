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
use Illuminate\Support\Facades\DB;

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
        $query->leftJoin('transactions', 'delivery_note_items.transaction_id', '=', 'transactions.id');
        $query->leftJoin('historic_assets', 'transactions.historic_asset_id', '=', 'historic_assets.id');
        $query->leftJoin('tax_categories', 'transactions.tax_category_id', '=', 'tax_categories.id');
        $query->leftJoin('orders', 'transactions.order_id', '=', 'orders.id');
        $query->leftJoin('currencies', 'orders.currency_id', '=', 'currencies.id');

        $query->where('delivery_note_items.quantity_required', '>', 0);

        return $query
            ->defaultSort('org_stocks.code')
            ->select(array_merge($this->getDeliveryNoteItemBaseSelect(), [
                'transactions.net_amount',
                'transactions.quantity_ordered',
                'tax_categories.rate as tax_rate',
                'currencies.code as currency_code',
                'historic_assets.code as product_code',
                DB::raw('(select count(*) from delivery_note_items siblings where siblings.transaction_id = delivery_note_items.transaction_id and siblings.delivery_note_id = delivery_note_items.delivery_note_id) as number_skos_in_product'),
            ]))
            ->allowedSorts(array_merge($this->getDeliveryNoteItemBaseSorts(), ['picking_position']))
            ->withPaginator('deliveryNoteItems', tableName: request()->route()->getName())
            ->withQueryString();
    }




}

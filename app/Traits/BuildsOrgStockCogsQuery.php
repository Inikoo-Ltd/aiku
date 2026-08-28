<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 05 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Traits;

use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait BuildsOrgStockCogsQuery
{
    protected function joinDispatchedQuantity(Builder $query, string $pivot = 'pivot'): Builder
    {
        return $query
            ->leftJoin('org_stocks', 'org_stocks.id', '=', "$pivot.org_stock_id")
            ->leftJoinLateral($this->dispatchedQuantitySubQuery($pivot), 'dispatched');
    }

    protected function dispatchedQuantitySubQuery(string $pivot = 'pivot'): Builder
    {
        return DB::connection('aiku_no_sticky')->table('delivery_note_items')
            ->selectRaw('COALESCE(SUM(delivery_note_items.quantity_dispatched), 0) as quantity')
            ->whereColumn('delivery_note_items.transaction_id', 'invoice_transactions.transaction_id')
            ->whereColumn('delivery_note_items.org_stock_id', "$pivot.org_stock_id")
            ->where('delivery_note_items.state', '!=', DeliveryNoteItemStateEnum::CANCELLED->value);
    }

    protected function cogsSelects(): array
    {
        $cogsOrgCurrency = 'COALESCE(dispatched.quantity, 0) * COALESCE(org_stocks.sku_value, 0)';
        $hasOrgExchange  = 'invoice_transactions.org_exchange > 0';

        return [
            DB::raw("SUM($cogsOrgCurrency) as cogs_org_currency"),
            DB::raw("SUM(CASE WHEN $hasOrgExchange THEN $cogsOrgCurrency * invoice_transactions.grp_exchange / invoice_transactions.org_exchange ELSE 0 END) as cogs_grp_currency"),
        ];
    }
}

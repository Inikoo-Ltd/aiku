<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Models\Ordering\Order;
use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class EnsurePartnerOrderPackedInMatches
{
    use AsAction;

    public function handle(Order $order): void
    {
        $orgPartner = OrgPartner::where('partner_id', $order->organisation_id)
            ->get()
            ->first(fn (OrgPartner $orgPartner) => in_array($order->customer_id, data_get($orgPartner->data, 'intercompany_customers', [])));

        if (!$orgPartner) {
            return;
        }

        $stockIds = DB::table('transactions')
            ->join('products', 'products.asset_id', 'transactions.asset_id')
            ->join('product_has_org_stocks', 'product_has_org_stocks.product_id', 'products.id')
            ->join('org_stocks', 'org_stocks.id', 'product_has_org_stocks.org_stock_id')
            ->where('transactions.order_id', $order->id)
            ->pluck('org_stocks.stock_id')
            ->unique()
            ->all();

        $this->guard($orgPartner, $stockIds);
    }

    public function guard(OrgPartner $orgPartner, array $stockIds): void
    {
        if ($mismatches = $this->mismatches($orgPartner, $stockIds)) {
            throw ValidationException::withMessages(['packed_in' => $mismatches]);
        }
    }

    public function mismatches(OrgPartner $orgPartner, array $stockIds): array
    {
        return $this->mismatchedRows($orgPartner, $stockIds)
            ->map(fn ($row) => __(':sko is packed in :seller at :seller_org and :buyer at :buyer_org, the SKO must be the same in both before it can be ordered between them', [
                'sko'        => $row->code,
                'seller'     => $row->seller_packed_in,
                'seller_org' => $orgPartner->partner->name,
                'buyer'      => $row->buyer_packed_in,
                'buyer_org'  => $orgPartner->organisation->name,
            ]))
            ->all();
    }

    public function mismatchedStockIds(OrgPartner $orgPartner, array $stockIds): array
    {
        return $this->mismatchedRows($orgPartner, $stockIds)->pluck('stock_id')->all();
    }

    private function mismatchedRows(OrgPartner $orgPartner, array $stockIds): Collection
    {
        if (!$stockIds) {
            return collect();
        }

        return DB::table('org_stocks as seller')
            ->join('org_stocks as buyer', function ($join) use ($orgPartner) {
                $join->on('buyer.stock_id', 'seller.stock_id')->where('buyer.organisation_id', $orgPartner->organisation_id);
            })
            ->where('seller.organisation_id', $orgPartner->partner_id)
            ->whereIn('seller.stock_id', $stockIds)
            ->whereRaw('coalesce(seller.packed_in, 1) <> coalesce(buyer.packed_in, 1)')
            ->get(['seller.stock_id', 'seller.code', DB::raw('coalesce(seller.packed_in, 1) as seller_packed_in'), DB::raw('coalesce(buyer.packed_in, 1) as buyer_packed_in')]);
    }
}

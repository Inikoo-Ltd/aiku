<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The search box on the supplier page: only this supplier's own purchase orders (the ones sent to it) and products.
 * Typesense filters by supplier, and every hit is checked again against the database before it is returned.
 */
class SearchSupplierPortal
{
    use AsAction;

    private const int LIMIT = 8;

    /**
     * @return array{products: list<array{code: string, name: string}>, orders: list<array{reference: string, state_label: string}>}
     */
    public function handle(Supplier $supplier, string $query): array
    {
        $productIds = SupplierProduct::search($query)->where('supplier_id', $supplier->id)->take(self::LIMIT)->keys();
        $orderIds   = PurchaseOrder::search($query)->where('supplier_id', $supplier->id)->take(self::LIMIT)->keys();

        $stateLabels = PurchaseOrderStateEnum::labels();

        return [
            'products' => SupplierProduct::where('supplier_id', $supplier->id)
                ->whereIn('id', $productIds)
                ->get(['code', 'name'])
                ->map(fn (SupplierProduct $product) => ['code' => $product->code, 'name' => $product->name])
                ->values()
                ->all(),
            'orders'   => PurchaseOrder::where('supplier_id', $supplier->id)
                ->where('parent_type', 'OrgSupplier')
                ->whereNotIn('state', [PurchaseOrderStateEnum::IN_PROCESS])
                ->whereIn('id', $orderIds)
                ->orderByDesc('submitted_at')
                ->get(['reference', 'state'])
                ->map(fn (PurchaseOrder $order) => ['reference' => $order->reference, 'state_label' => $stateLabels[$order->state->value] ?? $order->state->value])
                ->values()
                ->all(),
        ];
    }

    public function asController(string $token, Request $request): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $query = trim((string) $request->query('q'));
        if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
            return response()->json(['products' => [], 'orders' => []]);
        }

        return response()->json($this->handle($link->supplier, $query));
    }
}

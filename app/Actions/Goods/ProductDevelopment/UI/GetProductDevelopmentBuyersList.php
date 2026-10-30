<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-17h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Actions\Goods\ProductDevelopment\ProductDevelopmentPurchaseOrders;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentComponentStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentComponent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What purchasing needs to start producing an in-house development: every component across its SKUs, how
 * many are needed, whether it is approved for purchasing, and whether purchasing has confirmed it is bought.
 * Aiku does not track these purchases, so the confirmation is the record.
 */
class GetProductDevelopmentBuyersList
{
    use AsAction;
    use WithProductDevelopmentAuthorisation;

    /**
     * @return array<string, mixed>
     */
    public function handle(ProductDevelopment $productDevelopment, User $viewer): array
    {
        $canConfirm     = $this->productDevelopmentAccess()->canApproveComponents($viewer, $productDevelopment);
        $components     = $productDevelopment->components()->with(['tradeUnit', 'item.tradeUnit', 'purchaseConfirmedBy'])->get();
        $purchaseOrders = $productDevelopment->componentPurchaseOrders()->with('organisation')->orderBy('date')->get()->groupBy('pivot.trade_unit_id');
        $ordered        = $this->orderedQuantities($purchaseOrders->flatten()->pluck('id')->unique()->all(), $components->pluck('trade_unit_id')->unique()->all());

        $rows = $components->groupBy('trade_unit_id')->map(function (Collection $lines) use ($canConfirm, $purchaseOrders, $ordered) {
            /** @var ProductDevelopmentComponent $first */
            $first     = $lines->first();
            $tradeUnit = $first->tradeUnit;
            $approved  = $lines->every(fn (ProductDevelopmentComponent $line) => $line->status === ProductDevelopmentComponentStatusEnum::APPROVED);
            $confirmed = $lines->every(fn (ProductDevelopmentComponent $line) => $line->purchase_confirmed_at !== null);

            return [
                'trade_unit_id' => $tradeUnit->id,
                'code'          => $tradeUnit->code,
                'name'          => $tradeUnit->name,
                'route'         => ['name' => 'grp.trade_units.units.show', 'parameters' => ['tradeUnit' => $tradeUnit->slug]],
                'skus'          => $lines->map(fn (ProductDevelopmentComponent $line) => $line->item->tradeUnit->code)->unique()->values()->all(),
                'required'      => (float) $lines->sum('quantity'),
                'approved'      => $approved,
                'status'        => ProductDevelopmentComponentStatusEnum::labels()[$approved ? 'approved' : 'pending'],
                'purchase'      => $confirmed ? [
                    'confirmed_at' => $first->purchase_confirmed_at?->toIso8601String(),
                    'confirmed_by' => $first->purchaseConfirmedBy?->contact_name ?: $first->purchaseConfirmedBy?->username,
                    'note'         => $first->purchase_note,
                ] : null,
                'purchase_orders' => ($purchaseOrders->get($tradeUnit->id) ?? collect())->map(fn (PurchaseOrder $purchaseOrder) => [
                    ...ProductDevelopmentPurchaseOrders::row($purchaseOrder, ProductDevelopmentPurchaseOrders::MANUAL),
                    'organisation' => $purchaseOrder->organisation->name,
                    'ordered'      => $ordered[$purchaseOrder->id.'-'.$tradeUnit->id] ?? null,
                ])->values()->all(),
                'can_confirm'   => $canConfirm && $approved,
                'can_undo'      => $canConfirm && $confirmed,
            ];
        })->sortBy('code')->values();

        return [
            'rows'   => $rows->all(),
            'totals' => [
                'components' => $rows->count(),
                'approved'   => $rows->where('approved', true)->count(),
                'confirmed'  => $rows->filter(fn (array $row) => $row['purchase'] !== null)->count(),
            ],
            'route'  => ['name' => 'grp.models.product_development.component_purchase', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
            'link'   => $canConfirm ? [
                'route'        => ['name' => 'grp.models.product_development.component_purchase_order', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
                'search_route' => ['name' => 'grp.product_developments.json.purchase_orders', 'parameters' => ['productDevelopment' => $productDevelopment->slug]],
            ] : null,
        ];
    }

    /**
     * How many of each component each linked purchase order orders, in trade units, keyed "purchase order id-trade unit id".
     *
     * @param array<int, int> $purchaseOrderIds
     * @param array<int, int> $tradeUnitIds
     *
     * @return array<string, float>
     */
    private function orderedQuantities(array $purchaseOrderIds, array $tradeUnitIds): array
    {
        if ($purchaseOrderIds === [] || $tradeUnitIds === []) {
            return [];
        }

        return DB::table('purchase_order_transactions')
            ->join('model_has_trade_units', function ($join) {
                $join->on('model_has_trade_units.model_id', '=', 'purchase_order_transactions.org_stock_id')
                    ->where('model_has_trade_units.model_type', 'OrgStock');
            })
            ->whereIn('purchase_order_transactions.purchase_order_id', $purchaseOrderIds)
            ->whereIn('model_has_trade_units.trade_unit_id', $tradeUnitIds)
            ->whereNull('purchase_order_transactions.deleted_at')
            ->groupBy('purchase_order_transactions.purchase_order_id', 'model_has_trade_units.trade_unit_id')
            ->selectRaw('purchase_order_transactions.purchase_order_id, model_has_trade_units.trade_unit_id, sum(coalesce(purchase_order_transactions.quantity_ordered, 0) * coalesce(model_has_trade_units.quantity, 1)) as ordered')
            ->get()
            ->mapWithKeys(fn ($line) => [$line->purchase_order_id.'-'.$line->trade_unit_id => (float) $line->ordered])
            ->all();
    }
}

<?php

/*
 * Author Louis Perez
 * Created on 20-10-2026-10h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The purchase orders of a development: every PO that orders one of its SKUs' supplier products, and the
 * ones linked to it by hand.
 */
class ProductDevelopmentPurchaseOrders
{
    public const string AUTO = 'auto';
    public const string MANUAL = 'manual';

    /**
     * @return array<int, int>
     */
    public static function supplierProductIds(ProductDevelopment $productDevelopment): array
    {
        return $productDevelopment->items()->whereNotNull('supplier_product_id')->pluck('supplier_product_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public static function automatic(ProductDevelopment $productDevelopment): Collection
    {
        $supplierProductIds = self::supplierProductIds($productDevelopment);
        if ($supplierProductIds === []) {
            return collect();
        }

        return PurchaseOrder::query()
            ->where('group_id', $productDevelopment->group_id)
            ->whereHas('purchaseOrderTransactions', fn (Builder $transactions) => $transactions->whereIn('supplier_product_id', $supplierProductIds))
            ->with('organisation')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rows(ProductDevelopment $productDevelopment): array
    {
        $automatic = self::automatic($productDevelopment)->keyBy('id');
        $manual    = $productDevelopment->purchaseOrders()->with('organisation')->get()->keyBy('id');

        return $automatic->map(fn (PurchaseOrder $purchaseOrder) => self::row($purchaseOrder, self::AUTO))
            ->union($manual->reject(fn (PurchaseOrder $purchaseOrder) => $automatic->has($purchaseOrder->id))->map(fn (PurchaseOrder $purchaseOrder) => self::row($purchaseOrder, self::MANUAL)))
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(PurchaseOrder $purchaseOrder, string $linked): array
    {
        return [
            'id'        => $purchaseOrder->id,
            'reference' => $purchaseOrder->reference,
            'state'     => PurchaseOrderStateEnum::labels()[$purchaseOrder->state->value] ?? $purchaseOrder->state->value,
            'date'      => $purchaseOrder->date?->toDateString(),
            'linked'    => $linked,
            'sample'    => match (true) {
                !$purchaseOrder->sample_required        => null,
                $purchaseOrder->sample_approved_at !== null => 'approved',
                filled($purchaseOrder->sample_override_reason) => 'overridden',
                default                                 => 'required',
            },
            'route'     => ['name' => 'grp.org.procurement.purchase_orders.show', 'parameters' => ['organisation' => $purchaseOrder->organisation->slug, 'purchaseOrder' => $purchaseOrder->slug]],
        ];
    }

    /**
     * The developments a purchase order belongs to, with how: linked by hand, or through a SKU it orders.
     *
     * @return array<int, string> link type by development id
     */
    public static function developmentLinks(PurchaseOrder $purchaseOrder): array
    {
        $manual = ProductDevelopment::query()
            ->whereHas('purchaseOrders', fn (Builder $purchaseOrders) => $purchaseOrders->where('purchase_orders.id', $purchaseOrder->id))
            ->pluck('id')
            ->all();

        $supplierProductIds = $purchaseOrder->purchaseOrderTransactions()->whereNotNull('supplier_product_id')->pluck('supplier_product_id')->unique()->all();
        $automatic          = $supplierProductIds === [] ? [] : ProductDevelopment::query()
            ->whereHas('items', fn (Builder $items) => $items->whereIn('supplier_product_id', $supplierProductIds))
            ->pluck('id')
            ->all();

        $links = array_fill_keys($manual, self::MANUAL);
        foreach ($automatic as $productDevelopmentId) {
            $links[$productDevelopmentId] = self::AUTO;
        }

        return $links;
    }
}

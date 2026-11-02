<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Goods\TradeUnit\TradeUnitPackagingMaterialEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteCategoryEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\ProcurementNote;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What goods in checks on a delivery as it is unloaded: for each supplier on it and each of its products, who
 * packs them and in what, and the packaging and product notes procurement recorded. A failed check becomes an
 * incident on that supplier or product, linked to the purchase order the goods came on (INI-104).
 */
class GetStockDeliveryGoodsInChecklist
{
    use AsObject;

    public const array STATES = [
        StockDeliveryStateEnum::DISPATCHED,
        StockDeliveryStateEnum::RECEIVED,
        StockDeliveryStateEnum::CHECKED,
        StockDeliveryStateEnum::BOOKING_IN,
        StockDeliveryStateEnum::BOOKED_IN,
        StockDeliveryStateEnum::PLACED,
    ];

    /**
     * @return list<array{key: string, title: string, subtitle: string|null, subject: Supplier|SupplierProduct, purchase_order: PurchaseOrder|null, checks: list<array<string, mixed>>}>
     */
    public function handle(StockDelivery $stockDelivery): array
    {
        if (!in_array($stockDelivery->state, self::STATES, true)) {
            return [];
        }

        $items = $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->whereNotNull('supplier_product_id')
            ->with('supplierProduct.supplier')
            ->orderBy('id')
            ->get();

        $supplierProducts = $items->pluck('supplierProduct')->filter()->unique('id')->values();
        $suppliers        = $supplierProducts->pluck('supplier')->filter()->unique('id')->values();
        $notes            = $this->notes($stockDelivery, $suppliers->merge($supplierProducts));
        $results          = Arr::get($stockDelivery->data, 'goods_in_checks', []);

        $purchaseOrders          = $stockDelivery->purchaseOrders()->orderBy('purchase_orders.id')->get(['purchase_orders.id', 'purchase_orders.reference', 'purchase_orders.supplier_id']);
        $purchaseOrderOfProduct  = $this->purchaseOrderOfProduct($purchaseOrders, $supplierProducts);
        $purchaseOrderOfSupplier = fn (Supplier $supplier): ?PurchaseOrder => $purchaseOrders->firstWhere('supplier_id', $supplier->id)
            ?? $supplierProducts->where('supplier_id', $supplier->id)->map(fn (SupplierProduct $supplierProduct) => $purchaseOrderOfProduct($supplierProduct))->filter()->first();

        $sections = [];
        foreach ($suppliers as $supplier) {
            $sections[] = $this->section(
                'supplier:'.$supplier->id,
                $supplier->name,
                $supplier->code,
                $supplier,
                $purchaseOrderOfSupplier($supplier),
                $notes,
                $results
            );
        }
        foreach ($supplierProducts as $supplierProduct) {
            $sections[] = $this->section(
                'supplier_product:'.$supplierProduct->id,
                $supplierProduct->code,
                $supplierProduct->name,
                $supplierProduct,
                $purchaseOrderOfProduct($supplierProduct),
                $notes,
                $results
            );
        }

        return array_values(array_filter($sections, fn (array $section) => $section['checks'] !== []));
    }

    /**
     * The checklist for the page: no models, and a count of what is left.
     *
     * @return array{sections: list<array<string, mixed>>, total: int, checked: int, failed: int}
     */
    public function forPage(StockDelivery $stockDelivery): array
    {
        $sections = collect($this->handle($stockDelivery))
            ->map(fn (array $section) => Arr::except($section, ['subject', 'purchase_order']) + ['purchase_order' => $section['purchase_order']?->reference])
            ->values();
        $checks = $sections->flatMap(fn (array $section) => $section['checks']);

        return [
            'sections' => $sections->all(),
            'total'    => $checks->count(),
            'checked'  => $checks->whereNotNull('result')->count(),
            'failed'   => $checks->where('result', 'failed')->count(),
        ];
    }

    /**
     * @return array{key: string, title: string, subtitle: string|null, subject: Supplier|SupplierProduct, purchase_order: PurchaseOrder|null, checks: list<array<string, mixed>>}
     */
    private function section(string $key, string $title, ?string $subtitle, Supplier|SupplierProduct $subject, ?PurchaseOrder $purchaseOrder, Collection $notes, array $results): array
    {
        $checks = [];

        if ($subject->packing_method || $subject->packing_material) {
            $checks[] = [
                'key'      => $key.':packing',
                'label'    => __('Packing details'),
                'detail'   => implode(' · ', array_filter([
                    $subject->packing_method?->label(),
                    $subject->packing_material ? TradeUnitPackagingMaterialEnum::materials()[$subject->packing_material->value] ?? $subject->packing_material->value : null,
                ])),
                'category' => ProcurementNoteCategoryEnum::PACKAGING->value,
                'severity' => 'info',
            ];
        }

        foreach ($notes->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->id) as $note) {
            $checks[] = [
                'key'      => $key.':note:'.$note->id,
                'label'    => $note->title ?: $note->category->label(),
                'detail'   => $note->body,
                'category' => $note->category->value,
                'severity' => $note->severity->value,
            ];
        }

        return [
            'key'            => $key,
            'title'          => $title,
            'subtitle'       => $subtitle,
            'subject'        => $subject,
            'purchase_order' => $purchaseOrder,
            'checks'         => array_map(fn (array $check) => $check + [
                'result'      => Arr::get($results, $check['key'].'.result'),
                'checked_by'  => Arr::get($results, $check['key'].'.user'),
                'checked_at'  => Arr::get($results, $check['key'].'.at'),
                'incident_id' => Arr::get($results, $check['key'].'.incident_id'),
            ], $checks),
        ];
    }

    /**
     * The purchase order of the delivery that ordered a product: the one with a line for it, or the delivery's only order.
     *
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     */
    private function purchaseOrderOfProduct(Collection $purchaseOrders, Collection $supplierProducts): Closure
    {
        $orderedOn = $purchaseOrders->isEmpty() ? collect() : DB::table('purchase_order_transactions')
            ->whereIn('purchase_order_id', $purchaseOrders->pluck('id'))
            ->whereIn('supplier_product_id', $supplierProducts->pluck('id'))
            ->orderBy('purchase_order_id')
            ->pluck('purchase_order_id', 'supplier_product_id');

        return fn (SupplierProduct $supplierProduct): ?PurchaseOrder => $purchaseOrders->find($orderedOn->get($supplierProduct->id))
            ?? ($purchaseOrders->count() === 1 ? $purchaseOrders->first() : null);
    }

    /**
     * @param  Collection<int, Supplier|SupplierProduct>  $subjects
     * @return Collection<int, ProcurementNote>
     */
    private function notes(StockDelivery $stockDelivery, Collection $subjects): Collection
    {
        if ($subjects->isEmpty()) {
            return collect();
        }

        return ProcurementNote::active()
            ->forOrganisation($stockDelivery->organisation)
            ->whereIn('category', [ProcurementNoteCategoryEnum::PACKAGING, ProcurementNoteCategoryEnum::PRODUCT])
            ->where(function (Builder $query) use ($subjects) {
                foreach ($subjects->groupBy(fn ($subject) => $subject->getMorphClass()) as $type => $models) {
                    $query->orWhere(fn (Builder $query) => $query->where('subject_type', $type)->whereIn('subject_id', $models->pluck('id')));
                }
            })
            ->orderByDesc('is_pinned')
            ->orderBy('id')
            ->get();
    }
}

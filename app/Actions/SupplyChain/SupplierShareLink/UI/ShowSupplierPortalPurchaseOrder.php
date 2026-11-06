<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrderLabels;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One purchase order sent to the supplier, as the supplier sees it: what the order document it received already says
 * (products, quantities, its own prices, terms) and how far the order is up to hand over. Who placed it, our costs,
 * extra costs, notes, deliveries and containers stay out.
 */
class ShowSupplierPortalPurchaseOrder
{
    use AsAction;

    private const array RELATIONS = [
        'organisation',
        'agent.organisation',
        'currency',
        'purchaseOrderTransactions.supplierProduct.currency',
        'purchaseOrderTransactions.supplierProduct.image',
        'purchaseOrderTransactions.supplierProduct.tradeUnits.image',
        'purchaseOrderTransactions.orgStock',
    ];

    /**
     * @return array{order: array<string, mixed>, journey: array<string, mixed>|null, lines: list<array<string, mixed>>}
     */
    public function handle(Supplier $supplier, int $purchaseOrderId): array
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = IndexSupplierPortalPurchaseOrders::make()->sent($supplier)->findOrFail($purchaseOrderId);
        $purchaseOrder->loadMissing(self::RELATIONS);

        $open  = in_array($purchaseOrder->state, [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED], true);
        $lines = PdfPurchaseOrder::make()->lines($purchaseOrder);
        $data  = $purchaseOrder->data ?? [];

        return [
            'order'   => [
                'id'                    => $purchaseOrder->id,
                'reference'             => $purchaseOrder->reference,
                'organisation_name'     => $purchaseOrder->organisation->name,
                'agent_name'            => $purchaseOrder->agent?->organisation?->name,
                'state'                 => $purchaseOrder->state->value,
                'state_label'           => PurchaseOrderStateEnum::labels()[$purchaseOrder->state->value] ?? $purchaseOrder->state->value,
                'delivery_label'        => $open && $purchaseOrder->delivery_state ? (PurchaseOrderDeliveryStateEnum::labels()[$purchaseOrder->delivery_state->value] ?? null) : null,
                'open'                  => $open,
                'submitted_at'          => $purchaseOrder->submitted_at,
                'confirmed_at'          => $purchaseOrder->confirmed_at,
                'estimated_received_at' => $open ? $purchaseOrder->estimated_received_at : null,
                'settled_at'            => $purchaseOrder->state === PurchaseOrderStateEnum::SETTLED ? $purchaseOrder->settled_at : null,
                'currency_code'         => $purchaseOrder->currency->code,
                'total'                 => round($lines->sum(fn (PurchaseOrderTransaction $line) => (float) $line->net_amount), 2),
                'terms'                 => array_values(array_filter([
                    ['label' => __('Incoterm'), 'value' => Arr::get($data, 'incoterm')],
                    ['label' => __('Port of export'), 'value' => Arr::get($data, 'port_of_export')],
                    ['label' => __('Port of import'), 'value' => Arr::get($data, 'port_of_import')],
                    ['label' => __('Payment'), 'value' => Arr::get($data, 'payment_terms')],
                ], fn (array $term) => filled($term['value']))),
                'labels'                => $open
                    ? collect(PdfPurchaseOrderLabels::LEVELS)->mapWithKeys(fn (string $level) => [$level => PdfPurchaseOrderLabels::make()->url($purchaseOrder, [$level])])->all()
                    : null,
            ],
            'journey' => IndexSupplierPortalPurchaseOrderStages::make()->forPurchaseOrder($supplier, $purchaseOrder->id),
            'lines'   => $lines->map(fn (PurchaseOrderTransaction $line) => $this->line($line, $purchaseOrder))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(PurchaseOrderTransaction $line, PurchaseOrder $purchaseOrder): array
    {
        $product         = $line->supplierProduct;
        $imageOwner      = $product?->image_id ? $product : $product?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);
        $unitsPerCarton  = (float) ($product?->units_per_carton ?: 0);
        $perSupplierUnit = $product?->supplier_unit ? $product->unitsPerSupplierUnit() : 1.0;
        $unitCost        = (float) ($line->unit_cost ?? $product?->cost);
        $ourCode         = $product && $line->orgStock && $line->orgStock->code !== $product->code ? $line->orgStock->code : null;

        return [
            'id'               => $line->id,
            'code'             => $product?->code ?? $line->orgStock?->code,
            'our_code'         => $ourCode,
            'name'             => $product?->name ?? $line->orgStock?->name,
            'image'            => $imageOwner?->imageSources(80, 80),
            'units_per_carton' => $unitsPerCarton ?: null,
            'cartons'          => $unitsPerCarton ? round((float) $line->quantity_ordered / $unitsPerCarton, 3) : null,
            'quantity'         => round((float) $line->quantity_ordered / $perSupplierUnit, 3),
            'unit'             => $product?->supplier_unit ? $product->supplier_unit::labels()[$product->supplier_unit->value] : null,
            'unit_price'       => $unitCost * $perSupplierUnit,
            'amount'           => (float) $line->net_amount,
            'currency_code'    => $product?->currency?->code ?? $purchaseOrder->currency->code,
            'cancelled'        => $line->state === PurchaseOrderTransactionStateEnum::CANCELLED,
        ];
    }

    public function asController(string $token, int $purchaseOrder): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        return response()->json($this->handle($link->supplier, $purchaseOrder));
    }
}

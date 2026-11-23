<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use App\Models\Accounting\ReceivedEInvoice;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * A suggestion only: staff confirm it with MatchReceivedEInvoice. The supplier is suggested when exactly one supplier
 * of the organisation has the sender's Peppol id, then its tax/company number, then the same name; the order and the
 * delivery when exactly one of the organisation's has the document's order reference.
 */
class SuggestReceivedEInvoiceMatch
{
    use AsObject;

    /**
     * @return array{supplier_id: int|null, supplier_reason: string|null, purchase_order_id: int|null, stock_delivery_id: int|null}
     */
    public function handle(ReceivedEInvoice $receivedEInvoice): array
    {
        [$supplierId, $reason] = $this->supplier($receivedEInvoice);

        $byReference = fn (Builder $query) => $receivedEInvoice->order_reference
            ? $query->where('organisation_id', $receivedEInvoice->organisation_id)
                ->where('reference', $receivedEInvoice->order_reference)
                ->when($supplierId, fn (Builder $query) => $query->where('supplier_id', $supplierId))
                ->limit(2)->pluck('id')
            : collect();

        $purchaseOrders  = $byReference(PurchaseOrder::query());
        $stockDeliveries = $byReference(StockDelivery::query());

        return [
            'supplier_id'       => $supplierId,
            'supplier_reason'   => $reason,
            'purchase_order_id' => $purchaseOrders->count() === 1 ? $purchaseOrders->first() : null,
            'stock_delivery_id' => $stockDeliveries->count() === 1 ? $stockDeliveries->first() : null,
        ];
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    private function supplier(ReceivedEInvoice $receivedEInvoice): array
    {
        $suppliers = Supplier::query()
            ->whereNull('deleted_at')
            ->whereIn('id', fn ($query) => $query->select('supplier_id')->from('org_suppliers')->where('organisation_id', $receivedEInvoice->organisation_id))
            ->get(['id', 'name', 'company_name', 'identity_document_number', 'peppol_id']);

        $peppolId = $receivedEInvoice->sender_peppol_id;
        $numbers  = array_filter(array_map(fn (?string $number) => $this->normalisedNumber($number), [
            $receivedEInvoice->supplier_vat_number,
            $receivedEInvoice->supplier_tax_number,
            $receivedEInvoice->supplier_company_number,
            $peppolId && str_contains($peppolId, ':') ? substr($peppolId, strpos($peppolId, ':') + 1) : null,
        ]));
        $names = array_filter(array_map(fn (?string $name) => $this->normalisedName($name), [$receivedEInvoice->supplier_name, $receivedEInvoice->sender_name]));

        $rules = [
            'peppol_id'  => fn (Supplier $supplier) => $peppolId && $supplier->peppol_id && strcasecmp(trim($supplier->peppol_id), $peppolId) === 0,
            'tax_number' => fn (Supplier $supplier) => in_array($this->normalisedNumber($supplier->identity_document_number), $numbers, true),
            'name'       => fn (Supplier $supplier) => array_intersect(array_filter([$this->normalisedName($supplier->name), $this->normalisedName($supplier->company_name)]), $names) !== []
                && !($numbers && $this->normalisedNumber($supplier->identity_document_number) && !in_array($this->normalisedNumber($supplier->identity_document_number), $numbers, true)),
        ];

        foreach ($rules as $reason => $rule) {
            $found = $suppliers->filter($rule);
            if ($found->count() === 1) {
                return [$found->first()->id, $reason];
            }
        }

        return [null, null];
    }

    private function normalisedNumber(?string $number): ?string
    {
        $number = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$number));

        return strlen($number) >= 6 ? $number : null;
    }

    private function normalisedName(?string $name): ?string
    {
        $name = mb_strtolower(preg_replace('/[^\p{L}\p{N}]/u', '', (string)$name));

        return mb_strlen($name) >= 3 ? $name : null;
    }
}

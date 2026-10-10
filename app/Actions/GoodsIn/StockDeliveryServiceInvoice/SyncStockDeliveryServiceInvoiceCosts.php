<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryServiceInvoice;

use App\Actions\GoodsIn\StockDelivery\DeleteStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryCost;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCost;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Writes a delivery's cost checklist from the service invoices allocated to it, in the organisation currency:
 * shipping is the sum of its freight bills, duty of its duty bills, and each other bill is an extra row.
 * Import VAT bills are recoverable and stay out. Rows typed by hand are left alone until a bill of their type
 * arrives, and a row the bills filled goes back to pending once no bill of its type is left.
 */
class SyncStockDeliveryServiceInvoiceCosts
{
    use AsAction;

    public function handle(StockDelivery $stockDelivery): void
    {
        $allocations = DB::table('stock_delivery_service_invoice_allocations')
            ->join('stock_delivery_service_invoices', 'stock_delivery_service_invoices.id', 'stock_delivery_service_invoice_allocations.stock_delivery_service_invoice_id')
            ->whereNull('stock_delivery_service_invoices.deleted_at')
            ->where('stock_delivery_service_invoice_allocations.stock_delivery_id', $stockDelivery->id)
            ->orderBy('stock_delivery_service_invoices.date')
            ->get([
                'stock_delivery_service_invoices.id',
                'stock_delivery_service_invoices.type',
                'stock_delivery_service_invoices.issuer',
                'stock_delivery_service_invoices.reference',
                'stock_delivery_service_invoices.date',
                'stock_delivery_service_invoices.exchange',
                'stock_delivery_service_invoice_allocations.amount',
            ]);

        $this->syncExtras($stockDelivery, $allocations->where('type', StockDeliveryServiceInvoiceTypeEnum::OTHER->value)->keyBy('id'));

        foreach ([StockDeliveryServiceInvoiceTypeEnum::FREIGHT, StockDeliveryServiceInvoiceTypeEnum::DUTY] as $type) {
            $bills = $allocations->where('type', $type->value);
            $cost  = $stockDelivery->costs()->where('type', $type->costType())->first();

            if ($bills->isEmpty()) {
                if ($cost?->from_service_invoices) {
                    UpdateStockDeliveryCost::make()->action($cost, ['label' => null, 'amount' => null, 'received_at' => null, 'currency_id' => null, 'from_service_invoices' => false]);
                }
                continue;
            }

            $costData = $this->costData($stockDelivery, $bills) + ['is_na' => false, 'from_service_invoices' => true];

            if ($cost) {
                UpdateStockDeliveryCost::make()->action($cost, $costData);
            } else {
                StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => $type->costType()->value] + $costData);
            }
        }
    }

    private function syncExtras(StockDelivery $stockDelivery, Collection $bills): void
    {
        $rows = $stockDelivery->costs()->whereNotNull('stock_delivery_service_invoice_id')->get()->keyBy('stock_delivery_service_invoice_id');

        foreach ($rows as $billId => $row) {
            if (!$bills->has($billId)) {
                DeleteStockDeliveryCost::make()->action($row);
            }
        }

        foreach ($bills as $billId => $bill) {
            $costData = $this->costData($stockDelivery, collect([$bill]));

            if ($row = $rows->get($billId)) {
                /** @var StockDeliveryCost $row */
                UpdateStockDeliveryCost::make()->action($row, $costData);
            } else {
                StoreStockDeliveryCost::make()->action($stockDelivery, $costData + [
                    'type'                              => 'extra',
                    'from_service_invoices'             => true,
                    'stock_delivery_service_invoice_id' => $billId,
                ]);
            }
        }
    }

    /**
     * @return array{label: string, amount: float, received_at: string, currency_id: int, exchange: float|null}
     */
    private function costData(StockDelivery $stockDelivery, Collection $bills): array
    {
        $orgExchange = (float) $stockDelivery->org_exchange;

        return [
            'label'       => Str::limit($bills->map(fn (object $bill) => trim($bill->issuer.' '.$bill->reference))->unique()->implode(', '), 250),
            'amount'      => round($bills->sum(fn (object $bill) => (float) $bill->amount * (float) $bill->exchange), 2),
            'received_at' => $bills->max('date'),
            'currency_id' => $stockDelivery->organisation->currency_id,
            'exchange'    => $orgExchange > 0 ? 1 / $orgExchange : null,
        ];
    }
}

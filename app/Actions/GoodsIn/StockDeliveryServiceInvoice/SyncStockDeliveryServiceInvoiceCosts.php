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
use App\Actions\SupplyChain\AgentInvoice\ApplyAgentInvoiceCosting;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCost;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Writes a delivery's cost checklist from the service invoices allocated to it, in the organisation currency:
 * shipping is the sum of its freight and local transport bills on top of the freight the agent invoiced, duty of
 * its duty bills (customs duty, other import taxes), and every other bill is an extra row of its own. An anti-dumping
 * bill is such a row too: the user picks the tariff codes it applies to, which a re-sync keeps.
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

        $allocations = $allocations->groupBy(fn (object $bill) => StockDeliveryServiceInvoiceTypeEnum::from($bill->type)->costType()?->value);

        $this->syncExtras($stockDelivery, $allocations->get(StockDeliveryCostTypeEnum::EXTRA->value, collect())->keyBy('id'));

        foreach ([StockDeliveryCostTypeEnum::SHIPPING, StockDeliveryCostTypeEnum::DUTY] as $costType) {
            $bills = $allocations->get($costType->value, collect());
            $cost  = $stockDelivery->costs()->where('type', $costType)->first();

            $agentFreight = $costType === StockDeliveryCostTypeEnum::SHIPPING ? Arr::get($stockDelivery->data ?? [], ApplyAgentInvoiceCosting::FREIGHT) : null;

            if ($bills->isEmpty()) {
                if ($cost?->from_service_invoices) {
                    UpdateStockDeliveryCost::make()->action($cost, ($agentFreight ?? ['label' => null, 'amount' => null, 'received_at' => null]) + ['currency_id' => null, 'from_service_invoices' => false]);
                }
                continue;
            }

            $costData = $this->costData($stockDelivery, $bills) + ['is_na' => false, 'from_service_invoices' => true];

            if ($agentFreight && $cost && !$cost->from_service_invoices && !$cost->currency_id) {
                $agentFreight = [
                    'label'       => $cost->label,
                    'amount'      => $cost->is_na ? 0 : round((float) $cost->amount, 2),
                    'received_at' => $cost->received_at?->toDateString() ?? $agentFreight['received_at'],
                ];
                $stockDelivery->update(['data' => array_merge($stockDelivery->data, [ApplyAgentInvoiceCosting::FREIGHT => $agentFreight])]);
            }

            if ($agentFreight) {
                $orgExchange        = (float) $stockDelivery->org_exchange;
                $costData['amount'] = round($costData['amount'] + (float) $agentFreight['amount'] * ($orgExchange > 0 ? $orgExchange : 1), 2);
                $costData['label']  = Str::limit(implode(', ', array_filter([$agentFreight['label'], $costData['label']])), 250);
            }

            if ($cost) {
                UpdateStockDeliveryCost::make()->action($cost, $costData);
            } else {
                StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => $costType->value] + $costData);
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
            $costData = $this->costData($stockDelivery, collect([$bill])) + ['category' => $bill->type];

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
            'is_waiting_for_invoice' => false,
        ];
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentInvoice;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryCost;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStockDeliveryItemCost;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\SupplyChain\AgentInvoice;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Costs a placed container from its agent invoice, so nobody has to cost it by hand: each line costs what the agent
 * invoiced for it, scaled to what was received; the agent's freight becomes the shipping and its other charges the
 * extras, which the costing spreads over the lines; and the invoice is marked received. The costing then completes
 * by itself once our organisation has entered the customs, and the shipping when the freight was not on the invoice.
 * It goes through the costing actions, so stock is revalued by the costing and never written here; it applies once.
 */
class ApplyAgentInvoiceCosting
{
    use AsAction;

    public const string APPLIED_AT = 'agent_invoice_costing_applied_at';

    public function handle(StockDelivery $stockDelivery): ?StockDelivery
    {
        /** @var AgentInvoice|null $agentInvoice */
        $agentInvoice = $stockDelivery->agentInvoice()->first();

        if (!$agentInvoice
            || $agentInvoice->source === StockDeliveryInvoiceSourceEnum::ESTIMATED
            || $stockDelivery->state !== StockDeliveryStateEnum::PLACED
            || $stockDelivery->is_costed
            || $agentInvoice->currency_id !== $stockDelivery->currency_id
            || Arr::get($stockDelivery->data ?? [], self::APPLIED_AT)) {
            return null;
        }

        DB::transaction(function () use ($stockDelivery, $agentInvoice) {
            $items = $stockDelivery->items()->get()->keyBy('id');

            foreach ($agentInvoice->lines as $line) {
                /** @var StockDeliveryItem|null $item */
                $item = $items->get(Arr::get($line, 'stock_delivery_item_id'));
                if (!$item || $item->state === StockDeliveryItemStateEnum::CANCELLED) {
                    continue;
                }

                UpdateStockDeliveryItemCost::make()->action($item, ['cost_items' => $this->receivedCost($item, $line)]);
            }

            $receivedAt = $agentInvoice->date->toDateString();
            $charges    = collect($agentInvoice->charges ?? []);
            $freight    = $charges->where('type', UpdateAgentInvoiceCharges::CHARGE_FREIGHT);
            $shipping   = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::SHIPPING)->first();

            if ($freight->isNotEmpty() && !$shipping) {
                StoreStockDeliveryCost::make()->action($stockDelivery, [
                    'type'        => StockDeliveryCostTypeEnum::SHIPPING->value,
                    'label'       => $freight->pluck('description')->implode(', '),
                    'amount'      => round($freight->sum('amount'), 2),
                    'received_at' => $receivedAt,
                ]);
                $charges = $charges->reject(fn (array $charge) => ($charge['type'] ?? null) === UpdateAgentInvoiceCharges::CHARGE_FREIGHT);
            }

            foreach ($charges as $charge) {
                StoreStockDeliveryCost::make()->action($stockDelivery, [
                    'type'        => StockDeliveryCostTypeEnum::EXTRA->value,
                    'label'       => $charge['description'],
                    'amount'      => $charge['amount'],
                    'received_at' => $receivedAt,
                ]);
            }

            $invoice     = [
                'label'       => __('Invoice :number', ['number' => $agentInvoice->reference]),
                'amount'      => (float) $agentInvoice->total_amount,
                'received_at' => $receivedAt,
            ];
            $invoiceCost = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::AGENT_INVOICE)->first();

            if ($invoiceCost) {
                UpdateStockDeliveryCost::make()->action($invoiceCost, $invoice);
            } else {
                StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::AGENT_INVOICE->value, ...$invoice]);
            }

            $data = $stockDelivery->refresh()->data ?? [];
            data_set($data, self::APPLIED_AT, now()->toIso8601String());
            $stockDelivery->update(['data' => $data]);
        });

        return $stockDelivery->refresh();
    }

    /**
     * @param  array{quantity: float|int, amount: float|int}  $line
     */
    private function receivedCost(StockDeliveryItem $item, array $line): float
    {
        $invoiced = (float) $line['quantity'];
        $received = $item->state === StockDeliveryItemStateEnum::NOT_RECEIVED
            ? 0.0
            : (float) ($item->unit_quantity_checked ?? $item->unit_quantity);

        if ($invoiced <= 0) {
            return round((float) $line['amount'], 2);
        }

        return round((float) $line['amount'] * $received / $invoiced, 2);
    }
}

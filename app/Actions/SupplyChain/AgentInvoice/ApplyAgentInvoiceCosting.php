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
 * It goes through the costing actions, so stock is revalued by the costing and never written here; it applies once,
 * and only after the paying organisation has approved the agent's charges.
 */
class ApplyAgentInvoiceCosting
{
    use AsAction;

    public const string APPLIED_AT = 'agent_invoice_costing_applied_at';

    /**
     * The agent's freight that became the shipping row, kept so the local freight bills add to it instead of replacing it.
     */
    public const string FREIGHT = 'agent_invoice_freight';

    public function handle(StockDelivery $stockDelivery): ?StockDelivery
    {
        /** @var AgentInvoice|null $agentInvoice */
        $agentInvoice = $stockDelivery->agentInvoice()->first();

        if (!$agentInvoice
            || $agentInvoice->source === StockDeliveryInvoiceSourceEnum::ESTIMATED
            || !ApproveAgentInvoiceCharges::isApproved($agentInvoice)
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

            $agentFreight = null;

            if ($freight->isNotEmpty() && !$shipping) {
                $agentFreight = [
                    'label'       => $freight->pluck('description')->implode(', '),
                    'amount'      => round($freight->sum('amount'), 2),
                    'received_at' => $receivedAt,
                ];
                StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::SHIPPING->value, ...$agentFreight]);
                $charges = $charges->reject(fn (array $charge) => ($charge['type'] ?? null) === UpdateAgentInvoiceCharges::CHARGE_FREIGHT);
            }

            foreach ($charges as $charge) {
                $line = ($orgStockId = Arr::get($charge, 'org_stock_id'))
                    ? $items->first(fn (StockDeliveryItem $item) => $item->org_stock_id === $orgStockId && $item->state !== StockDeliveryItemStateEnum::CANCELLED)
                    : null;

                StoreStockDeliveryCost::make()->action($stockDelivery, array_filter([
                    'type'                   => StockDeliveryCostTypeEnum::EXTRA->value,
                    'label'                  => $charge['description'],
                    'amount'                 => $charge['amount'],
                    'received_at'            => $receivedAt,
                    'stock_delivery_item_id' => $line?->id,
                ], fn ($value) => $value !== null));
            }

            $invoice     = [
                'label'       => __('Invoice :number', ['number' => $agentInvoice->reference]),
                'amount'      => (float) $agentInvoice->total_amount,
                'received_at' => $receivedAt,
                'is_waiting_for_invoice' => false,
            ];
            $invoiceCost = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::AGENT_INVOICE)->first();

            if ($invoiceCost) {
                UpdateStockDeliveryCost::make()->action($invoiceCost, $invoice);
            } else {
                StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::AGENT_INVOICE->value, ...$invoice]);
            }

            $data = $stockDelivery->refresh()->data ?? [];
            data_set($data, self::APPLIED_AT, now()->toIso8601String());
            if ($agentFreight) {
                data_set($data, self::FREIGHT, $agentFreight);
            }
            $stockDelivery->update(['data' => $data]);
        });

        return $stockDelivery->refresh();
    }

    /**
     * What an entered agent invoice still waits for before handle() puts it in the costing (HELP-4594): until then the
     * costing has no invoice row, which must not read as "invoice not received". Null when there is nothing to wait for.
     * A costed delivery, or a placed one whose invoice was never applied (entered while it was costed, then reopened;
     * or AWChina-142, placed 6 Nov 2026 without it), gets nothing more from handle(): that is said as it is, without
     * guessing why.
     */
    public static function waitingFor(StockDelivery $stockDelivery): ?string
    {
        /** @var AgentInvoice|null $agentInvoice */
        $agentInvoice = $stockDelivery->agentInvoice()->with('currency')->first();

        if (!$agentInvoice
            || $agentInvoice->source === StockDeliveryInvoiceSourceEnum::ESTIMATED
            || Arr::get($stockDelivery->data ?? [], self::APPLIED_AT)) {
            return null;
        }

        $reference   = ['reference' => $agentInvoice->reference];
        $notInCosting = __('Invoice :reference is entered but is not in the costing.', $reference);

        $isApproved = ApproveAgentInvoiceCharges::isApproved($agentInvoice);
        $isPlaced   = $stockDelivery->state === StockDeliveryStateEnum::PLACED;

        return match (true) {
            $agentInvoice->currency_id !== $stockDelivery->currency_id => __('Invoice :reference entered in :currency and the delivery is in :delivery, so it does not go into the costing by itself.', $reference + ['currency' => $agentInvoice->currency?->code, 'delivery' => $stockDelivery->currency?->code]),
            $stockDelivery->is_costed,
            in_array($stockDelivery->state, [StockDeliveryStateEnum::CANCELLED, StockDeliveryStateEnum::NOT_RECEIVED], true) => $notInCosting,
            !$isApproved && !$isPlaced => __('Invoice :reference entered, it goes into the costing once its charges are approved and the delivery is placed.', $reference),
            !$isApproved               => __('Invoice :reference entered, it goes into the costing once its charges are approved.', $reference),
            !$isPlaced                 => __('Invoice :reference entered, it goes into the costing once the delivery is placed.', $reference),
            default                    => $notInCosting,
        };
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

        return round((float) $line['amount'] * min($received, $invoiced) / $invoiced, 2);
    }
}

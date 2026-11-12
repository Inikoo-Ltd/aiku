<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\Hydrators;

use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Moves an agreed purchase order's state to the furthest stage it reached (GetPurchaseOrderStages, the same stages the
 * PO page shows), so lists, boards, filters and counters read where the order is from purchase_orders.state. Only an
 * agreed, unfinished order moves, between Agreed and Homeward Bound: a person drafts, submits, agrees, cancels or marks
 * an order not received, and goods-in settles it, so a submitted order stays Submitted, also with a deposit paid or
 * production recorded, until it is agreed. An agreed order with no deposit owed reads Agreed, which the PO page leaves
 * unreached until a later stage (HELP-4315). It runs wherever a stage input changes: the order's own dates and state
 * (model hooks), its lines (CalculatePurchaseOrderTotalAmounts), its deliveries (PurchaseOrderHydrateDeliveryState,
 * customs), the agent warehouse (RecordPurchaseOrderHandedOverWhenReady, goods receipts), container contents and the
 * agent's journey settings (UpdateAgent), so a step back, like an undispatch, moves it back.
 */
class PurchaseOrderHydrateState
{
    use AsAction;
    use HasPurchaseOrderHydrators;

    public string $commandSignature = 'procurement:hydrate-purchase-order-state {--id=* : Only these purchase order ids}';

    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrderStateEnum
    {
        $state = $this->resolve($purchaseOrder);

        if ($purchaseOrder->state !== $state) {
            PurchaseOrder::whereKey($purchaseOrder->id)->update(['state' => $state->value]);
            $purchaseOrder->state = $state;
            $purchaseOrder->syncOriginalAttribute('state');
            $this->purchaseOrderHydrate($purchaseOrder);
        }

        return $state;
    }

    public function resolve(PurchaseOrder $purchaseOrder): PurchaseOrderStateEnum
    {
        if ($purchaseOrder->trashed() || !$purchaseOrder->state->isAgreed()) {
            return $purchaseOrder->state;
        }

        $reached = collect(GetPurchaseOrderStages::make()->handle($purchaseOrder))
            ->filter(fn (array $stage) => $stage['reached'])
            ->keys()
            ->last();

        return self::stateOfStage(PurchaseOrderStageEnum::tryFrom((string) $reached));
    }

    /**
     * The state an agreed order has at a stage. Counted & Costed is not one of them: goods-in settles the order
     * once none of its lines is still awaited, so an order whose deliveries are all in stays Homeward Bound until then.
     */
    public static function stateOfStage(?PurchaseOrderStageEnum $stage): PurchaseOrderStateEnum
    {
        return match ($stage) {
            PurchaseOrderStageEnum::IN_PRODUCTION => PurchaseOrderStateEnum::IN_PRODUCTION,
            PurchaseOrderStageEnum::IN_WAREHOUSE  => PurchaseOrderStateEnum::IN_WAREHOUSE,
            PurchaseOrderStageEnum::READY         => PurchaseOrderStateEnum::READY,
            PurchaseOrderStageEnum::HANDED_OVER   => PurchaseOrderStateEnum::HANDED_OVER,
            PurchaseOrderStageEnum::ARRIVED, PurchaseOrderStageEnum::RECEIVED => PurchaseOrderStateEnum::ARRIVED,
            default                               => PurchaseOrderStateEnum::CONFIRMED,
        };
    }

    /**
     * @param iterable<int|null> $purchaseOrderIds
     */
    public function forPurchaseOrderIds(iterable $purchaseOrderIds): void
    {
        $ids = collect($purchaseOrderIds)->filter()->unique()->values();
        if ($ids->isNotEmpty()) {
            PurchaseOrder::whereIn('id', $ids)->get()->each(fn (PurchaseOrder $purchaseOrder) => $this->handle($purchaseOrder));
        }
    }

    public function forStockDelivery(StockDelivery $stockDelivery): void
    {
        $stockDelivery->purchaseOrders()->get()->each(fn (PurchaseOrder $purchaseOrder) => $this->handle($purchaseOrder));
    }

    public function asCommand(Command $command): int
    {
        $query = PurchaseOrder::query()->whereIn('state', PurchaseOrderStateEnum::agreed())->orderBy('id');
        if ($ids = $command->option('id')) {
            $query->whereIn('id', $ids);
        }

        $changed = 0;
        $total   = 0;
        $query->chunkById(200, function ($purchaseOrders) use (&$changed, &$total) {
            foreach ($purchaseOrders as $purchaseOrder) {
                $before = $purchaseOrder->state;
                if ($this->handle($purchaseOrder) !== $before) {
                    $changed++;
                }
                $total++;
            }
        });

        $command->info("$changed of $total agreed purchase orders changed state");

        return 0;
    }
}

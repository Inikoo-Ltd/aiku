<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\Hydrators;

use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Stores the furthest stage a purchase order reached (GetPurchaseOrderStages, the same stages the PO page shows), so
 * lists, boards and filters read where the order is from one column instead of stopping at Agreed. A draft, cancelled
 * or not received order has no stage. An agreed order is at least at Agreement, also when no deposit is owed, which the
 * PO page leaves unreached until a later stage (HELP-4315), and a submitted one stays Submitted until it is agreed, also
 * with a deposit paid while it waits on answers to its lines. It runs wherever a stage input changes: the order's own
 * dates and state (model hooks), its lines (CalculatePurchaseOrderTotalAmounts), its deliveries
 * (PurchaseOrderHydrateDeliveryState, customs), the agent warehouse (RecordPurchaseOrderHandedOverWhenReady, goods
 * receipts), container contents and the agent's journey settings (UpdateAgent), so a step back, like an undispatch,
 * moves it back.
 */
class PurchaseOrderHydrateStage
{
    use AsAction;

    public string $commandSignature = 'procurement:hydrate-purchase-order-stage {--id=* : Only these purchase order ids} {--open : Only submitted and agreed orders}';

    private const array WITHOUT_STAGE = [
        PurchaseOrderStateEnum::IN_PROCESS,
        PurchaseOrderStateEnum::CANCELLED,
        PurchaseOrderStateEnum::NOT_RECEIVED,
    ];

    public function handle(PurchaseOrder $purchaseOrder): ?PurchaseOrderStageEnum
    {
        $stage = $this->resolve($purchaseOrder);

        if ($purchaseOrder->stage !== $stage) {
            PurchaseOrder::withTrashed()->whereKey($purchaseOrder->id)->update(['stage' => $stage?->value]);
            $purchaseOrder->stage = $stage;
            $purchaseOrder->syncOriginalAttribute('stage');
        }

        return $stage;
    }

    public function resolve(PurchaseOrder $purchaseOrder): ?PurchaseOrderStageEnum
    {
        if ($purchaseOrder->trashed() || in_array($purchaseOrder->state, self::WITHOUT_STAGE, true)) {
            return null;
        }

        $reached = collect(GetPurchaseOrderStages::make()->handle($purchaseOrder))
            ->filter(fn (array $stage) => $stage['reached'])
            ->keys()
            ->last();

        $stage = PurchaseOrderStageEnum::tryFrom((string) $reached) ?? PurchaseOrderStageEnum::SUBMITTED;

        if ($stage === PurchaseOrderStageEnum::SUBMITTED && $purchaseOrder->state !== PurchaseOrderStateEnum::SUBMITTED) {
            return PurchaseOrderStageEnum::DEPOSIT_PAID;
        }

        if ($stage === PurchaseOrderStageEnum::DEPOSIT_PAID && $purchaseOrder->state === PurchaseOrderStateEnum::SUBMITTED) {
            return PurchaseOrderStageEnum::SUBMITTED;
        }

        return $stage;
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
        $query = PurchaseOrder::query()->orderBy('id');
        if ($ids = $command->option('id')) {
            $query->whereIn('id', $ids);
        }
        if ($command->option('open')) {
            $query->whereIn('state', [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED]);
        }

        $changed = 0;
        $total   = 0;
        $query->chunkById(200, function ($purchaseOrders) use (&$changed, &$total) {
            foreach ($purchaseOrders as $purchaseOrder) {
                $before = $purchaseOrder->stage;
                if ($this->handle($purchaseOrder) !== $before) {
                    $changed++;
                }
                $total++;
            }
        });

        $command->info("$changed of $total purchase orders changed stage");

        return 0;
    }
}

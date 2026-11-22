<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Procurement;

use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Open orders submitted before the lead time fix of 22 Nov 2026 kept the arrival date worked out from the slowest
 * line's mean over every organisation (POsk-0032: 270 days). Only orders whose date is still that calculation are
 * recomputed: a date typed by hand or promised in a chase stays. The old figures are kept in data.eta_recomputed.
 * Run hydrate:supplier-product-lead-time first. Nothing is written without --apply.
 */
class RepairPurchaseOrderEstimatedArrival
{
    use AsAction;

    public string $commandSignature = 'repair:purchase_order_estimated_arrival {--apply : Write the changes; without it nothing is written}';

    /**
     * @return Collection<int, array{purchase_order: PurchaseOrder, days_before: int, days_after: int}>
     */
    public function handle(bool $apply = false): Collection
    {
        $estimator = UpdatePurchaseOrderStateToSubmitted::make();

        $changes = PurchaseOrder::query()
            ->whereIn('state', PurchaseOrderStateEnum::openValues())
            ->whereNotNull('submitted_at')
            ->whereNotNull('estimated_delivery_days')
            ->whereRaw("coalesce(data->>'estimated_receiving_date', '') = ''")
            ->whereRaw('estimated_received_at::date = (submitted_at + estimated_delivery_days * interval \'1 day\')::date')
            ->orderBy('id')
            ->get()
            ->map(fn (PurchaseOrder $purchaseOrder) => [
                'purchase_order' => $purchaseOrder,
                'days_before'    => $purchaseOrder->estimated_delivery_days,
                'days_after'     => $estimator->estimateDeliveryDays($purchaseOrder),
            ])
            ->filter(fn (array $change) => $change['days_after'] !== null && $change['days_after'] !== $change['days_before'])
            ->values();

        if ($apply) {
            $changes->each(function (array $change) {
                $purchaseOrder = $change['purchase_order'];
                $purchaseOrder->update([
                    'estimated_delivery_days' => $change['days_after'],
                    'estimated_received_at'   => $purchaseOrder->submitted_at->clone()->addDays($change['days_after']),
                    'data'                    => [
                        ...($purchaseOrder->data ?? []),
                        'eta_recomputed' => [
                            'at'                         => now()->toIso8601String(),
                            'estimated_delivery_days'    => $change['days_before'],
                            'estimated_received_at'      => $purchaseOrder->estimated_received_at?->toDateString(),
                        ],
                    ],
                ]);
            });
        }

        return $changes;
    }

    public function asCommand(Command $command): int
    {
        $apply   = (bool) $command->option('apply');
        $changes = $this->handle($apply);

        $command->table(
            ['Purchase order', 'Submitted', 'Days before', 'Days after', 'Arrival after'],
            $changes->map(fn (array $change) => [
                $change['purchase_order']->reference,
                $change['purchase_order']->submitted_at->toDateString(),
                $change['days_before'],
                $change['days_after'],
                $change['purchase_order']->submitted_at->clone()->addDays($change['days_after'])->toDateString(),
            ])->all()
        );

        $command->info(($apply ? 'Recomputed ' : 'Would recompute ').$changes->count().' purchase orders.');
        if (!$apply) {
            $command->line('Nothing was written. Run again with --apply to write.');
        }

        return 0;
    }
}

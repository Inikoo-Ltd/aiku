<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\CrossDispatch;

use App\Actions\Procurement\OrgPartner\GetPartnerLandedCost;
use App\Actions\Traits\AsUniqueUntilProcessingAction;
use App\Enums\Dispatching\CrossDispatch\CrossDispatchChargeStateEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\CrossDispatchCharge;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Shipment;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\DB;

class CostCrossDispatchCharge implements ShouldBeUniqueUntilProcessing
{
    use AsUniqueUntilProcessingAction;

    public string $commandSignature = 'cross-dispatch:cost-charges';

    public string $commandDescription = 'Cost the open charges between companies whose goods could not be costed yet';

    public string $jobQueue = 'default';

    public int $jobUniqueFor = 600;

    public const int MOVEMENT_WAIT_HOURS = 24;

    public function getJobUniqueId(CrossDispatchCharge $charge): string
    {
        return (string) $charge->id;
    }

    /**
     * INI-484. Prices an open charge in the dispatching company's currency, from the terms written at dispatch.
     * Goods: each line at what its picks took off the shelf (the pick movements' value, FIFO), or at the
     * dispatching company's landed cost when a pick's movement has no value; a line with neither leaves the goods
     * uncosted. A pick whose movement is still queued is waited for: the costing comes back later, and after
     * MOVEMENT_WAIT_HOURS the line takes the landed cost. Parcels from the note's packing list (its shipments when it
     * has none), the fee per parcel of the terms, and the shipping typed on every shipment, or none with source
     * missing. Costed once the goods are; a reversed or already costed charge is left alone.
     */
    public function handle(CrossDispatchCharge $charge): void
    {
        DB::transaction(function () use ($charge) {
            $charge = CrossDispatchCharge::lockForUpdate()->find($charge->id);
            if (!$charge || $charge->state !== CrossDispatchChargeStateEnum::OPEN || $charge->costed_at) {
                return;
            }

            $deliveryNote = $charge->deliveryNote;
            if (!$deliveryNote) {
                return;
            }

            $waitsForMovements = $charge->dispatched_at->gt(now()->subHours(self::MOVEMENT_WAIT_HOURS));
            $items             = $deliveryNote->deliveryNoteItems()->where('quantity_dispatched', '>', 0)->get();
            $landedCosts       = GetPartnerLandedCost::make()->handle($items->pluck('org_stock_id')->unique()->values()->all());
            $lines             = $items->map(fn (DeliveryNoteItem $item) => $this->line($item, $landedCosts, $waitsForMovements))->values()->all();

            $shipments     = $deliveryNote->shipments()->get();
            $numberParcels = count($deliveryNote->parcels ?? []) ?: ((int) $shipments->sum('number_parcels') ?: 1);
            $shippingTyped = $shipments->isNotEmpty() && $shipments->every(fn (Shipment $shipment) => $shipment->cost !== null);
            $goodsCosted   = collect($lines)->every(fn (array $line) => $line['amount'] !== null);

            if (collect($lines)->contains('cost_source', 'waiting_for_stock_movement')) {
                self::dispatch($charge)->delay(now()->addMinutes(15))->afterCommit();
            }

            $charge->update([
                'lines'                => $lines,
                'number_parcels'       => $numberParcels,
                'dispatch_fee'         => round((float) ($charge->terms['dispatch_fee_per_parcel'] ?? 0) * $numberParcels, 2),
                'shipping_amount'      => $shippingTyped ? round((float) $shipments->sum('cost'), 2) : null,
                'shipping_cost_source' => $shippingTyped ? 'typed' : 'missing',
                'goods_amount'         => $goodsCosted ? round(collect($lines)->sum('amount'), 2) : null,
                'costed_at'            => $goodsCosted ? now() : null,
            ]);
        });
    }

    /**
     * @param  array<int, float>  $landedCosts
     * @return array{delivery_note_item_id: int, org_stock_id: int, quantity: float, unit_cost: float|null, amount: float|null, cost_source: string}
     */
    private function line(DeliveryNoteItem $item, array $landedCosts, bool $waitsForMovements): array
    {
        $quantity = (float) $item->quantity_dispatched;
        $picks    = $item->pickings()->where('type', PickingTypeEnum::PICK)->where('quantity', '>', 0)->get(['id', 'org_stock_movement_id']);
        $moved    = DB::table('org_stock_movements')
            ->whereIn('id', $picks->pluck('org_stock_movement_id')->filter()->unique()->all())
            ->selectRaw('coalesce(sum(abs(quantity)), 0) as quantity, coalesce(sum(abs(org_amount)), 0) as amount, bool_or(coalesce(org_amount, 0) = 0) as has_unvalued')
            ->first();

        $unitCost = null;
        $source   = 'missing';
        $movementsQueued = $picks->contains(fn ($pick) => !$pick->org_stock_movement_id);
        if ($movementsQueued && $waitsForMovements) {
            $source = 'waiting_for_stock_movement';
        } elseif ($picks->isNotEmpty() && !$movementsQueued && !$moved->has_unvalued && $moved->quantity > 0) {
            $unitCost = round($moved->amount / $moved->quantity, 4);
            $source   = 'picked_stock';
        } elseif (($landedCosts[$item->org_stock_id] ?? 0) > 0) {
            $unitCost = round((float) $landedCosts[$item->org_stock_id], 4);
            $source   = 'landed_cost';
        }

        return [
            'delivery_note_item_id' => $item->id,
            'org_stock_id'          => $item->org_stock_id,
            'quantity'              => $quantity,
            'unit_cost'             => $unitCost,
            'amount'                => $unitCost === null ? null : round($unitCost * $quantity, 2),
            'cost_source'           => $source,
        ];
    }

    public function asCommand(Command $command): int
    {
        $charges = CrossDispatchCharge::where('state', CrossDispatchChargeStateEnum::OPEN)->whereNull('costed_at')->get();
        $charges->each(fn (CrossDispatchCharge $charge) => $this->handle($charge));
        $command->info(sprintf('Costed %d of %d open charges waiting for a cost.', $charges->filter(fn (CrossDispatchCharge $charge) => $charge->fresh()->costed_at)->count(), $charges->count()));

        return 0;
    }
}

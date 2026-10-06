<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\GoodsIn;

use App\Actions\GoodsIn\StockDelivery\CancelStockDelivery;
use App\Actions\Traits\WithOrganisationSource;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * HELP-3693: when a delivery was deleted in Aurora and made again, Aiku fetched the new copy but kept
 * the old one open. Cancels the old copy only when Aurora no longer has it, a placed delivery with the
 * same reference exists, and nobody has worked on it in Aiku since the fetch. Anything else is listed
 * for the buyers to review.
 */
class CancelStaleAuroraStockDeliveries
{
    use AsAction;
    use WithOrganisationSource;

    public string $commandSignature = 'repair:cancel_stale_aurora_stock_deliveries {organisation} {--apply}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $apply        = (bool) $command->option('apply');

        $this->getOrganisationSource($organisation)->initialisation($organisation);

        $cancelled = 0;
        foreach ($this->getCandidates($organisation) as $stockDelivery) {
            $line = "$stockDelivery->id $stockDelivery->slug $stockDelivery->reference {$stockDelivery->state->value} $stockDelivery->source_id";

            if ($this->existsInAurora($stockDelivery)) {
                $command->line("REVIEW still in Aurora: $line");
                continue;
            }
            if ($stockDelivery->number_purchase_orders > 0 || $stockDelivery->purchaseOrders()->exists()) {
                $command->line("REVIEW linked to a purchase order: $line");
                continue;
            }
            if ($this->hasLinesNeverReceived($stockDelivery)) {
                $command->line("REVIEW has products not received on any other delivery from this supplier: $line");
                continue;
            }
            if ($this->touchedInAiku($stockDelivery)) {
                $command->line("REVIEW changed in Aiku: $line");
                continue;
            }

            $command->line(($apply ? 'CANCELLED ' : 'WOULD CANCEL ').$line);
            if ($apply) {
                CancelStockDelivery::make()->handle($stockDelivery);
            }
            $cancelled++;
        }

        $command->info(($apply ? 'Cancelled ' : 'Would cancel ').$cancelled);

        return 0;
    }

    private function getCandidates(Organisation $organisation): iterable
    {
        return StockDelivery::where('organisation_id', $organisation->id)
            ->whereNotNull('source_id')
            ->whereNull('delivery_note_id')
            ->whereNotIn('state', [StockDeliveryStateEnum::PLACED, StockDeliveryStateEnum::CANCELLED, StockDeliveryStateEnum::NOT_RECEIVED])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('stock_deliveries as placed')
                    ->whereColumn('placed.organisation_id', 'stock_deliveries.organisation_id')
                    ->whereColumn('placed.reference', 'stock_deliveries.reference')
                    ->whereColumn('placed.parent_type', 'stock_deliveries.parent_type')
                    ->whereColumn('placed.parent_id', 'stock_deliveries.parent_id')
                    ->whereColumn('placed.id', '!=', 'stock_deliveries.id')
                    ->whereNull('placed.deleted_at')
                    ->where('placed.state', StockDeliveryStateEnum::PLACED);
            })
            ->orderBy('id')
            ->get();
    }

    private function existsInAurora(StockDelivery $stockDelivery): bool
    {
        try {
            return DB::connection('aurora')->table('Supplier Delivery Dimension')
                ->where('Supplier Delivery Key', explode(':', $stockDelivery->source_id)[1])
                ->exists();
        } catch (Throwable) {
            return true;
        }
    }

    private function hasLinesNeverReceived(StockDelivery $stockDelivery): bool
    {
        return $stockDelivery->items()
            ->whereNotExists(function ($query) use ($stockDelivery) {
                $query->select(DB::raw(1))
                    ->from('stock_delivery_items as received')
                    ->join('stock_deliveries as received_delivery', 'received_delivery.id', 'received.stock_delivery_id')
                    ->whereColumn('received.org_stock_id', 'stock_delivery_items.org_stock_id')
                    ->whereNull('received.deleted_at')
                    ->where('received_delivery.state', StockDeliveryStateEnum::PLACED)
                    ->where('received_delivery.organisation_id', $stockDelivery->organisation_id)
                    ->where('received_delivery.parent_type', $stockDelivery->parent_type)
                    ->where('received_delivery.parent_id', $stockDelivery->parent_id)
                    ->whereBetween('received_delivery.created_at', [$stockDelivery->created_at->copy()->subDays(30), $stockDelivery->created_at->copy()->addDays(180)]);
            })
            ->exists();
    }

    private function touchedInAiku(StockDelivery $stockDelivery): bool
    {
        $fetchedAt = ($stockDelivery->last_fetched_at ?? $stockDelivery->fetched_at)?->copy()->addMinute();

        if (!$fetchedAt || $stockDelivery->updated_at > $fetchedAt) {
            return true;
        }

        $items = $stockDelivery->items();

        return (clone $items)->where('updated_at', '>', $fetchedAt)->exists()
            || (clone $items)->where('unit_quantity_placed', '>', 0)->exists()
            || (clone $items)->whereHas('sowings')->exists();
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 May 2025 19:35:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Actions\Traits\WithOrganisationsArgument;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;

class CalculateDeliveryNoteTotalAmounts extends OrgAction
{
    use WithOrganisationsArgument;

    public function handle(DeliveryNote $deliveryNote): void
    {
        $estimatedWeight = $deliveryNote->deliveryNoteItems()->whereIn('state', [
            DeliveryNoteItemStateEnum::PACKED,
            DeliveryNoteItemStateEnum::FINALISED,
            DeliveryNoteItemStateEnum::DISPATCHED
        ])->sum('estimated_picked_weight');

        $estimatedWeight += $deliveryNote->deliveryNoteItems()->whereIn('state', [
            DeliveryNoteItemStateEnum::UNASSIGNED,
            DeliveryNoteItemStateEnum::QUEUED,
            DeliveryNoteItemStateEnum::HANDLING,
            DeliveryNoteItemStateEnum::HANDLING_BLOCKED
        ])->sum('estimated_required_weight');


        $deliveryNote->update([
            'estimated_weight' => $estimatedWeight,
            'effective_weight' => $deliveryNote->weight === null ? $estimatedWeight : $deliveryNote->weight
        ]);


        $deliveryNote->stats->update([
            'number_items' => $deliveryNote->deliveryNoteItems()->count()
        ]);

    }

    public string $commandSignature = 'delivery_note:totals {--s|slugs=}';

    public function asCommand(Command $command): int
    {
        $exitCode = 0;
        if (!$command->option('slugs')) {
            if ($command->argument('organisations')) {
                $this->organisation = $this->getOrganisations($command)->first();
            }

            $this->loopAll($command);
        } else {
            $slug  = $command->option('slugs');
            $order = DeliveryNote::where('slug', $slug)->first();
            if ($order) {
                $this->handle($order);
                $command->line("Delivery Note $order->reference hydrated 💦");
            } else {
                $command->error("Model not found");
                $exitCode = 1;
            }
        }

        return $exitCode;
    }

    protected function loopAll(Command $command): void
    {
        $command->withProgressBar(DeliveryNote::all(), function ($model) {
            if ($model) {
                $this->handle($model);
            }
        });
        $command->info("");
    }

}

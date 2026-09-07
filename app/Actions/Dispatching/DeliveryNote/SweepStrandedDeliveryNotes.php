<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Aug 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNote\UpdateState\AutoFinishWaitingDeliveryNote;
use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

/**
 * Defence in depth behind the write paths, not a repair of its own: it re-derives every blocked
 * note with the same two idempotent actions every pick, edit and sync already run, so a note that
 * stays blocked after this is blocked for a reason. A note it finds blocked with nothing blocking
 * it is a write path that forgot, and that is reported rather than quietly swept.
 */
class SweepStrandedDeliveryNotes
{
    use AsAction;

    public string $commandSignature = 'delivery_note:sweep_stranded {--dry : Report only}';

    /**
     * @return array{checked: int, stranded: string[], released: string[]}
     */
    public function handle(bool $dry = false): array
    {
        $stats = ['checked' => 0, 'stranded' => [], 'released' => []];

        DeliveryNote::where('state', DeliveryNoteStateEnum::HANDLING_BLOCKED)
            ->where('updated_at', '<', now()->subMinutes(30))
            ->orderBy('id')
            ->each(function (DeliveryNote $deliveryNote) use ($dry, &$stats) {
                $stats['checked']++;

                if (!$deliveryNote->hasBlockingItems()) {
                    $stats['stranded'][] = $deliveryNote->reference;
                }

                if ($dry) {
                    return;
                }

                foreach ($deliveryNote->deliveryNoteItems()->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)->get() as $deliveryNoteItem) {
                    CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);
                }

                AutoFinishWaitingDeliveryNote::run($deliveryNote->refresh());

                if ($deliveryNote->refresh()->state != DeliveryNoteStateEnum::HANDLING_BLOCKED) {
                    $stats['released'][] = $deliveryNote->reference;
                }
            });

        if ($stats['stranded']) {
            Sentry::captureMessage(
                'Delivery notes blocked with nothing blocking them: '.implode(', ', $stats['stranded']),
                Sentry\Severity::warning()
            );
        }

        return $stats;
    }

    public function asCommand(Command $command): int
    {
        $stats = $this->handle((bool)$command->option('dry'));

        $command->info("Checked {$stats['checked']} blocked delivery notes");
        $command->line('Stranded (blocked, nothing blocking): '.(implode(', ', $stats['stranded']) ?: 'none'));
        $command->line('Released: '.(implode(', ', $stats['released']) ?: 'none'));

        return 0;
    }
}

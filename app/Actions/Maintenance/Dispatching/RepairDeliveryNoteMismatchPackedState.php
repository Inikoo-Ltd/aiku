<?php

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Dispatching\DeliveryNoteItem\CheckAndCompleteDeliveryNotePacking;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;

class RepairDeliveryNoteMismatchPackedState
{
    use WithActionUpdate;

    public function handle(DeliveryNote $deliveryNote, Command $command)
    {
        $command->info("Repairing Delivery Note: {$deliveryNote->reference}");
        CheckAndCompleteDeliveryNotePacking::run($deliveryNote);
        $deliveryNote->refresh();
        $order = $deliveryNote->orders->first();

        $command->info("Delivery Note Current State: {$deliveryNote->state->value}");
        $command->info("Order Current State: {$order->state->value}");
        $command->info("Finished Process");
    }

    public string $commandSignature = 'repair:delivery_note_mismatch_packed_state {delivery_note?}';

    public function asCommand(Command $command): void
    {
        $deliveryNote = DeliveryNote::where('slug', $command->argument('delivery_note'))
            ->firstOrFail();

        if ($deliveryNote) {
            $this->handle($deliveryNote, $command);
        }
    }
}

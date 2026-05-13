<?php

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToPacked;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;

class RepairDeliveryNoteMismatchPackedState
{
    use WithActionUpdate;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(DeliveryNote $deliveryNote, Command $command)
    {

        $order = $deliveryNote->orders->first();
        if ($order->state == OrderStateEnum::PACKING) {
            $command->info("Repairing Delivery Note: $deliveryNote->organisation_id $deliveryNote->slug");
            UpdateOrderStateToPacked::make()->action($order, $deliveryNote);
        }
    }

    public string $commandSignature = 'repair:delivery_note_mismatch_packed_state {delivery_note?}';

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asCommand(Command $command): void
    {
        if ($command->argument('delivery_note')) {
            $deliveryNote = DeliveryNote::where('state', DeliveryNoteStateEnum::PACKED)->where('slug', $command->argument('delivery_note'))
                ->firstOrFail();
            $this->handle($deliveryNote, $command);

            return;
        }

        $aikuShops = Shop::where('is_aiku', true)->pluck('id')->toArray();
        foreach ($aikuShops as $shopId) {
            $deliveryNotes = DeliveryNote::where('state', DeliveryNoteStateEnum::PACKED)->where('shop_id', $shopId)->get();
            /** @var DeliveryNote $deliveryNote */
            foreach ($deliveryNotes as $deliveryNote) {
                $this->handle($deliveryNote, $command);
            }
        }
    }
}

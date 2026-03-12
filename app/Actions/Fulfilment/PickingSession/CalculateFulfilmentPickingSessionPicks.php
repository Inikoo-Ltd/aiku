<?php

namespace App\Actions\Fulfilment\PickingSession;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Models\Inventory\PickingSession;
use Illuminate\Console\Command;

class CalculateFulfilmentPickingSessionPicks extends OrgAction
{
    use WithActionUpdate;

    public function handle(PickingSession $pickingSession): PickingSession
    {
        $pickingPercentage = 0;
        $packingPercentage = 0;

        $itemsRequired = (float)($pickingSession->palletReturnItems()
            ->where('pallet_return_items.state', '!=', PalletReturnItemStateEnum::CANCEL)
            ->sum('quantity_ordered') ?? 0);

        $itemsPicked = (float)$pickingSession->palletReturnItems()->sum('quantity_picked');

        $itemsPacked = (float)$pickingSession->palletReturnItems()->sum('quantity_dispatched');

        // Picking percentage: picked vs. ordered
        if ($itemsRequired > 0) {
            $pickingPercentage = min(($itemsPicked / $itemsRequired) * 100, 100);
        }


        if ($itemsPicked > 0) {
            $packingPercentage = min(($itemsPacked / $itemsPicked) * 100, 100);
        }

        $pickingPercentage = round($pickingPercentage, 2);
        $packingPercentage = round($packingPercentage, 2);


        $pickingSession = $this->update($pickingSession, [
            'quantity_picked'    => $itemsPicked,
            'quantity_packed'    => $itemsPacked,
            'picking_percentage' => $pickingPercentage,
            'packing_percentage' => $packingPercentage
        ]);

        return $pickingSession;
    }

    public function action(PickingSession $pickingSession): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, []);

        return $this->handle($pickingSession);
    }

    public function getCommandSignature(): string
    {
        return 'fulfilment_picking_session:calculate {picking_session}';
    }

    public function getCommandDescription(): string
    {
        return 'Calculate fulfilment picking session picks';
    }

    public function asCommand(Command $command): int
    {
        $pickingSession = PickingSession::where('slug', $command->argument('picking_session'))->firstOrFail();

        $this->handle($pickingSession);

        return 0;
    }
}

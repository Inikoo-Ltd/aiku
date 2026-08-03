<?php

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNoteItem;
use Lorisleiva\Actions\ActionRequest;

class FetchDeliveryNoteItemImage extends OrgAction
{
    public function handle(DeliveryNoteItem $deliveryNoteItem): ?array
    {
        return $deliveryNoteItem->orgStock?->tradeUnits->first()?->imageSources(64, 64) ?? [];
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request)
    {
        $this->initialisation($deliveryNoteItem->organisation, $request);

        return $this->handle($deliveryNoteItem);
    }

    public function jsonResponse(array $deliveryNoteItemImage): ?array
    {
        return $deliveryNoteItemImage;
    }
}

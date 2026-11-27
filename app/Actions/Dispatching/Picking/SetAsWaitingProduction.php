<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 16:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteWorkAuthorisation;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

/**
 * Restockers park an item waiting in the warehouse until production makes it (HELP-4975).
 * The quantity stays waiting for the warehouse, so the item is still picked from the same
 * row once it is made; only the list it shows in changes.
 */
class SetAsWaitingProduction extends OrgAction
{
    use WithDeliveryNoteWorkAuthorisation;
    use AsAction;
    use WithAttributes;

    private DeliveryNoteItem $deliveryNoteItem;
    protected User $user;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): DeliveryNoteItem
    {
        if (!data_get($this->organisation->settings, 'orders.allow_waiting', false)) {
            abort(403, 'Waiting is not enabled for this organisation');
        }

        $isWaitingProduction = (bool)$modelData['waiting_production'];

        if ($isWaitingProduction && !$deliveryNoteItem->has_waiting_warehouse) {
            abort(422, 'Only items waiting in the warehouse can wait for production');
        }

        $deliveryNoteItem->update([
            'waiting_production_at' => $isWaitingProduction ? ($deliveryNoteItem->waiting_production_at ?? now()) : null,
        ]);
        DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);

        return $deliveryNoteItem;
    }

    public function rules(): array
    {
        return [
            'waiting_production' => ['required', 'boolean'],
        ];
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->user             = $request->user();
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromDeliveryNoteItem($deliveryNoteItem, $request);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }

    public function action(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): DeliveryNoteItem
    {
        $this->asAction         = true;
        $this->user             = $user;
        $this->deliveryNoteItem = $deliveryNoteItem;

        $this->initialisationFromDeliveryNoteItem($deliveryNoteItem, $modelData);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }
}

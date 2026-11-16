<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteWorkAuthorisation;
use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateItems;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydratePacker;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\PickedBay\DetachDeliveryNoteFromPickedBays;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToPacking;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StartPackingDeliveryNote extends OrgAction
{
    use WithDeliveryNoteWorkAuthorisation;
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;
    use WithUnprintedLeafletsGuard;

    /**
     * @throws \Throwable
     */
    private DeliveryNote $deliveryNote;
    private bool $isPackingBenchRequired = false;

    public function handle(DeliveryNote $deliveryNote, User $user, ?int $packingBenchId = null): DeliveryNote
    {
        $oldState = $deliveryNote->state;

        if (in_array($deliveryNote->state, [
            DeliveryNoteStateEnum::CANCELLED,
            DeliveryNoteStateEnum::DISPATCHED,
            DeliveryNoteStateEnum::FINALISED,
            DeliveryNoteStateEnum::PACKING,

        ])) {
            return $deliveryNote;
        }

        if ($deliveryNote->hasBlockingItems()) {
            abort(422, __('Cannot start packing: some items are waiting for a replacement decision or warehouse release'));
        }

        if ($deliveryNote->hasUnprintedLeaflets()) {
            abort(422, __('Cannot start packing: every insert must be printed first'));
        }

        if ($deliveryNote->hasNothingPicked()) {
            abort(422, DeliveryNote::nothingPickedMessage());
        }

        data_set($modelData, 'packing_at', now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::PACKING->value);
        data_set($modelData, 'packer_user_id', $user->id);
        if ($packingBenchId) {
            data_set($modelData, 'packing_bench_id', $packingBenchId);
        }


        $deliveryNote = DB::transaction(function () use ($deliveryNote, $modelData, $oldState) {
            /*
             * Customer service can add a product to a picked note, which walks it back to
             * handling under the same lock (HELP-3856). Without re-reading here, a packer
             * starting at that moment would stamp packing over a line nobody has picked.
             */
            $currentState = DeliveryNote::whereKey($deliveryNote->id)->lockForUpdate()->value('state');
            if ($currentState !== $oldState) {
                abort(409, __('The order has just been changed, refresh and pick the new items first'));
            }

            $deliveryNote = UpdateDeliveryNote::run($deliveryNote, $modelData);

            if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                UpdateOrderStateToPacking::make()->action($deliveryNote->orders->first());
            }

            DB::table('delivery_note_items')
                ->where('state', DeliveryNoteItemStateEnum::PICKED->value)
                ->where('delivery_note_id', $deliveryNote->id)
                ->update(['state' => DeliveryNoteItemStateEnum::PACKING->value]);

            DeliveryNoteHydrateItems::dispatch($deliveryNote)->delay($this->hydratorsDelay);

            DetachDeliveryNoteFromPickedBays::run($deliveryNote);

            return $deliveryNote;
        });


        $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
        $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::PACKING);

        DeliveryNoteHydratePacker::run($deliveryNote->id);
        return $deliveryNote;
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote|RedirectResponse
    {
        if ($notification = $this->unprintedLeafletsNotification($deliveryNote, __('Every insert must be printed before packing can start.'))) {
            return $notification;
        }

        $this->deliveryNote          = $deliveryNote;
        $this->isPackingBenchRequired = $deliveryNote->warehouse->packingBenches()->where('status', true)->exists();
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $request->user(), Arr::get($this->validatedData, 'packing_bench'));
    }

    public function rules(): array
    {
        return [
            'packing_bench' => [
                $this->isPackingBenchRequired ? 'required' : 'nullable',
                'integer',
                Rule::exists('packing_benches', 'id')
                    ->where('warehouse_id', $this->deliveryNote->warehouse_id)
                    ->where('status', true)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, User $user, array $modelData = []): DeliveryNote
    {
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $modelData);

        return $this->handle($deliveryNote, $user, Arr::get($this->validatedData, 'packing_bench'));
    }
}

<?php

/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-13h-37m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\BatchCode\Hydrators\BatchCodeHydrateDeliveryNotes;
use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Dispatching\Picking\Traits\AutoIgnoreZeroQuantityItems;
use App\Actions\Inventory\OrgStockMovement\AllocateOrgStockMovementBatches;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Inventory\OrgStockMovement\UpdateOrgStockMovement;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\OrgStockMovement;
use App\Actions\OrgAction;
use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteWorkAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\BatchCode;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdatePicking extends OrgAction
{
    use WithDeliveryNoteWorkAuthorisation;
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use AutoIgnoreZeroQuantityItems;

    private Picking $picking;
    private ?User $user = null;

    /**
     * @throws \Throwable
     */
    public function handle(Picking $picking, array $modelData, ?User $user = null): Picking|bool
    {
        $this->user ??= $user;

        if (Arr::has($modelData, 'quantity') && Arr::get($modelData, 'quantity') == 0) {
            return DeletePicking::make()->action($picking, $this->user);
        }

        $picking = DB::transaction(fn () => $this->updatePickingAndItsMovement($picking, $modelData));

        /** @var DeliveryNoteItem $deliveryNoteItem */
        $deliveryNoteItem = $picking->deliveryNoteItem;


        CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);

        $this->ignoreZeroQuantityItems($deliveryNoteItem->deliveryNote, $this->user);

        return $picking;
    }

    /**
     * The pick row is locked so this cannot interleave with StorePickingOrgStockMovement: either
     * the movement exists and is moved to the new quantity, or the queued job has not run yet
     * and will read the new quantity itself.
     */
    private function updatePickingAndItsMovement(Picking $picking, array $modelData): Picking
    {
        $picking     = Picking::lockForUpdate()->findOrFail($picking->id);
        $oldQuantity = $picking->quantity;

        if (Arr::has($modelData, 'quantity') && in_array($picking->type, [PickingTypeEnum::PICK, PickingTypeEnum::MAGIC_PICK], true)) {
            $deliveryNoteItemForClamp = $picking->deliveryNoteItem;
            /* Outstanding includes this picking's own quantity, so any decrease is always allowed */
            $outstanding = (float)$deliveryNoteItemForClamp->quantity_required
                - ((float)$deliveryNoteItemForClamp->quantity_picked - (float)$picking->quantity)
                - (float)$deliveryNoteItemForClamp->quantity_waiting_warehouse
                - (float)$deliveryNoteItemForClamp->quantity_waiting_crm;

            if ($outstanding <= 0) {
                abort(422, 'Nothing left to pick: the required quantity is already picked or waiting');
            }

            $modelData['quantity'] = min((float)$modelData['quantity'], $outstanding, $this->quantityAvailableInLocation($picking));
        }

        $oldBatchCodeId = $picking->batch_code_id;
        $picking        = $this->update($picking, $modelData);
        $batchChanged   = $oldBatchCodeId != $picking->batch_code_id;

        $movement = $picking->orgStockMovement;
        if ($movement && ($oldQuantity != $picking->quantity || $batchChanged)) {
            $batches = $picking->batch_code_id ? [['batch_code_id' => $picking->batch_code_id, 'quantity' => (float)$picking->quantity]] : [];

            if ($this->locationCountedSince($movement)) {
                $this->pickAgainAfterTheCount($picking, $movement, $batches);
            } else {
                $movementData = ['quantity' => -($picking->quantity)];
                if ($picking->batch_code_id || $batchChanged) {
                    $movementData['batches'] = $batches;
                }

                UpdateOrgStockMovement::make()->action($movement, $movementData);
            }
        }

        if (!$picking->org_stock_movement_id && $picking->type == PickingTypeEnum::PICK && (float)$picking->quantity > 0) {
            StorePickingOrgStockMovement::dispatch($picking->id, $this->user?->id ?? $picking->picker_user_id)->afterCommit();
        }

        if ($batchChanged) {
            foreach (array_filter([$oldBatchCodeId, $picking->batch_code_id]) as $batchCodeId) {
                BatchCodeHydrateDeliveryNotes::dispatch(BatchCode::find($batchCodeId))->afterCommit();
            }
        }

        if (!$picking->batch_code_id) {
            SplitPickingByBatch::run($picking);
        }

        return $picking->refresh();
    }

    private function locationCountedSince(OrgStockMovement $movement): bool
    {
        return OrgStockMovement::where('org_stock_id', $movement->org_stock_id)
            ->where('location_id', $movement->location_id)
            ->where('class', OrgStockMovementClassEnum::HELPER)
            ->where('date', '>', $movement->date)
            ->exists();
    }

    /**
     * A count after the pick already holds what the pick took, and a location's level is its
     * last count plus the movements dated after it. Editing the pick's movement in place would
     * change the shelf with a change dated before the count, invisible to that sum. So the old
     * movement is given back and the pick is taken again, both dated now.
     *
     * @param array<int, array{batch_code_id: int, quantity: float}> $batches
     */
    private function pickAgainAfterTheCount(Picking $picking, OrgStockMovement $movement, array $batches): void
    {
        StoreOrgStockMovement::run(
            $movement->orgStock,
            $movement->location,
            [
                'quantity' => -(float)$movement->quantity,
                'type'     => OrgStockMovementTypeEnum::CANCEL_PICKED,
                'user_id'  => $this->user?->id,
                'batches'  => AllocateOrgStockMovementBatches::make()->movedBatches($movement),
            ],
            $picking
        );

        StoreOrgStockMovement::run(
            $movement->orgStock,
            $movement->location,
            [
                'quantity' => -(float)$picking->quantity,
                'type'     => OrgStockMovementTypeEnum::PICKED,
                'user_id'  => $this->user?->id,
                'batches'  => $batches,
            ],
            $picking
        );

        $picking->refresh();
    }

    /**
     * What this picking can be raised to without sending the location negative. Once its
     * movement is stored the location already has this picking's quantity taken out of it, so
     * that amount is headroom the picking gets to keep; before that it is not.
     */
    private function quantityAvailableInLocation(Picking $picking): float
    {
        $locationOrgStock = LocationOrgStock::where('location_id', $picking->location_id)
            ->where('org_stock_id', $picking->org_stock_id)
            ->first();

        if (!$locationOrgStock) {
            return (float)$picking->quantity;
        }

        if (!$picking->org_stock_movement_id) {
            return (float)$locationOrgStock->quantity;
        }

        return (float)$locationOrgStock->quantity + (float)$picking->quantity;
    }

    public function rules(): array
    {
        return [
            'type'              => ['sometimes', Rule::enum(PickingTypeEnum::class)],
            'not_picked_reason' => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
            'not_picked_note'   => ['sometimes', 'string'],
            'quantity'          => ['sometimes', 'numeric'],
            'batch_code_id'     => ['sometimes', 'nullable', 'integer', 'exists:batch_codes,id'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Picking $picking, ActionRequest $request): void
    {
        $this->user = $request->user();
        $this->picking = $picking;
        $this->initialisationFromShop($picking->shop, $request);

        $this->handle($picking, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Picking $picking, array $modelData): Picking|bool
    {
        $this->picking = $picking;
        $this->initialisationFromShop($picking->shop, $modelData);

        return $this->handle($picking, $this->validatedData);
    }
}

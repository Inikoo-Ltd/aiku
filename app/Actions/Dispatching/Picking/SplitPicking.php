<?php

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\OrgAction;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Dispatching\Picking;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SplitPicking extends OrgAction
{
    use AsAction;
    use WithAttributes;

    private User|null $user = null;

    public function handle(Picking $picking, float $splitQuantity): Picking
    {
        return DB::transaction(function () use ($picking, $splitQuantity): Picking {
            $originalQty = (float) $picking->quantity;
            $newOriginalQty = $originalQty - $splitQuantity;

            $picking->update(['quantity' => $newOriginalQty]);

            $originalMovement = $picking->orgStockMovement;
            $newMovement = null;

            if ($originalMovement !== null) {
                $originalQuantity = (float) $originalMovement->quantity;
                $perUnitCost      = $originalQuantity != 0.0 ? abs((float) $originalMovement->org_amount / $originalQuantity) : 0.0;
                $grpRatio         = (float) $originalMovement->org_amount != 0.0 ? (float) $originalMovement->grp_amount / (float) $originalMovement->org_amount : 1.0;

                $originalMovement->update([
                    'quantity'   => -$newOriginalQty,
                    'org_amount' => round(-$newOriginalQty * $perUnitCost, 3),
                    'grp_amount' => round(-$newOriginalQty * $perUnitCost * $grpRatio, 3),
                ]);

                $newMovement = StoreOrgStockMovement::run(
                    $picking->orgStock,
                    $picking->location,
                    [
                        'quantity'   => -$splitQuantity,
                        'org_amount' => round(-$splitQuantity * $perUnitCost, 3),
                        'grp_amount' => round(-$splitQuantity * $perUnitCost * $grpRatio, 3),
                        'type'       => OrgStockMovementTypeEnum::PICKED,
                        'user_id'    => $this->user?->id,
                    ]
                );
            }

            $newPicking = $picking->replicate();
            $newPicking->quantity = $splitQuantity;
            $newPicking->batch_code_id = null;
            if ($newMovement !== null) {
                $newPicking->org_stock_movement_id = $newMovement->id;
            } else {
                $newPicking->org_stock_movement_id = null;
            }
            $newPicking->save();

            if (app()->environment('production')) {
                SavePickingInAurora::dispatch($picking);
                SavePickingInAurora::dispatch($newPicking);
            }


            CalculateDeliveryNoteItemTotalPicked::make()->action($picking->deliveryNoteItem);

            return $newPicking;
        });
    }

    public function asController(Picking $picking, ActionRequest $request): void
    {
        $this->user = request()->user();
        $this->initialisationFromShop($picking->shop, $request);
        $splitQuantity = (float) $request->input('split_quantity');

        if ($splitQuantity <= 0 || $splitQuantity >= (float) $picking->quantity) {
            abort(422, __('Invalid split quantity'));
        }

        $this->handle($picking, $splitQuantity);
    }
}

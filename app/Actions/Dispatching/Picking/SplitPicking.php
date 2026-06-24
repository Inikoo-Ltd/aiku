<?php

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Dispatching\Picking;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SplitPicking extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Picking $picking, float $splitQuantity): Picking
    {
        return DB::transaction(function () use ($picking, $splitQuantity): Picking {
            $originalQty = (float) $picking->quantity;
            $newOriginalQty = $originalQty - $splitQuantity;

            $picking->update(['quantity' => $newOriginalQty]);

            $originalMovement = $picking->orgStockMovement;
            $newMovement = null;

            if ($originalMovement !== null) {
                $originalMovement->update([
                    'quantity' => -$newOriginalQty,
                    'org_amount' => -$newOriginalQty * $picking->orgStock->value_in_locations,
                    'grp_amount' => -$newOriginalQty * $picking->orgStock->value_in_locations * GetCurrencyExchange::run($picking->orgStock->organisation->currency, $picking->orgStock->group->currency),
                ]);

                $newMovement = StoreOrgStockMovement::run(
                    $picking->orgStock,
                    $picking->location,
                    [
                        'quantity' => -$splitQuantity,
                        'type'     => OrgStockMovementTypeEnum::PICKED
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
        $this->initialisationFromShop($picking->shop, $request);
        $splitQuantity = (float) $request->input('split_quantity');

        if ($splitQuantity <= 0 || $splitQuantity >= (float) $picking->quantity) {
            abort(422, __('Invalid split quantity'));
        }

        $this->handle($picking, $splitQuantity);
    }
}

<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\GoodsIn\StockDeliveryItem\Traits\WithStockDeliveryItemStatePropagation;
use App\Actions\Inventory\OrgStockMovement\DeleteOrgStockMovement;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelStockDelivery extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use AsAction;
    use HasStockDeliveryHydrators;
    use WithActionUpdate;
    use WithStockDeliveryItemStatePropagation;

    public int $hydratorsDelay = 0;

    private StockDelivery $stockDelivery;

    private const CANCELLABLE_STATES = [
        StockDeliveryStateEnum::RECEIVED,
        StockDeliveryStateEnum::CHECKED,
    ];

    public function afterValidator(Validator $validator): void
    {
        if (!in_array($this->stockDelivery->state, self::CANCELLABLE_STATES, true)) {
            $validator->errors()->add('state', __('You can not cancel this stock delivery with state :state', ['state' => $this->stockDelivery->state->value]));
        }
    }

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->get()
            ->each(fn (StockDeliveryItem $stockDeliveryItem) => $this->cancelItem($stockDeliveryItem));

        $stockDelivery->update([
            'state'        => StockDeliveryStateEnum::CANCELLED,
            'cancelled_at' => now(),
        ]);

        UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery);

        StockDeliveriesHydrateItems::run($stockDelivery);
        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery;
    }

    private function cancelItem(StockDeliveryItem $stockDeliveryItem): void
    {
        foreach ($stockDeliveryItem->sowings as $sowing) {
            if ($sowing->orgStockMovement) {
                DeleteOrgStockMovement::run($sowing->orgStockMovement);
            }

            $sowing->delete();
        }

        $stockDeliveryItem = $this->update($stockDeliveryItem, [
            'state'                => StockDeliveryItemStateEnum::CANCELLED,
            'cancelled_at'         => now(),
            'unit_quantity'        => 0,
            'unit_quantity_placed' => 0,
            'net_amount'           => 0,
            'grp_net_amount'       => 0,
            'org_net_amount'       => 0,
            'gross_amount'         => 0,
            'grp_gross_amount'     => 0,
            'org_gross_amount'     => 0,
        ]);

        $this->propagateStockDeliveryItemStateChange($stockDeliveryItem);
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery);
    }

    public function action(StockDelivery $stockDelivery): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, []);

        return $this->handle($stockDelivery);
    }

    public function htmlResponse(StockDelivery $stockDelivery): RedirectResponse
    {
        return redirect()->back();
    }
}

<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-16 13:50
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\GoodsIn\StockDelivery\AddPurchaseOrdersToStockDelivery;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The space a line takes in the container. Null falls back to the cbm of its cartons; a line packed inside
 * another line (SetStockDeliveryItemPackedInside) takes no space whatever is set here.
 */
class SetStockDeliveryItemCbmInContainer extends OrgAction
{
    public const array EDITABLE_STATES = [
        StockDeliveryStateEnum::IN_PROCESS,
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
    ];

    public const string ITEM_VOLUME_SQL = 'case when '.SetStockDeliveryItemPackedInside::IS_PACKED_INSIDE_SQL.' then 0 else coalesce(sdi.cbm_in_container, sp.cbm * sdi.unit_quantity / nullif(sp.units_per_carton, 0)) end';

    private StockDeliveryItem $stockDeliveryItem;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return AddPurchaseOrdersToStockDelivery::userCanAdd($this->stockDeliveryItem->stockDelivery, $request->user());
    }

    public static function canBeEdited(StockDeliveryItem $stockDeliveryItem): bool
    {
        $stockDelivery = $stockDeliveryItem->stockDelivery;

        return $stockDelivery
            && $stockDeliveryItem->state !== StockDeliveryItemStateEnum::CANCELLED
            && in_array($stockDelivery->state, self::EDITABLE_STATES, true)
            && !$stockDelivery->isManagedByPartner();
    }

    public function handle(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $stockDeliveryItem->update(['cbm_in_container' => $modelData['cbm_in_container']]);

        return $stockDeliveryItem;
    }

    public function rules(): array
    {
        return [
            'cbm_in_container' => ['present', 'nullable', 'numeric', 'gte:0', 'max:999999'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!self::canBeEdited($this->stockDeliveryItem)) {
            $validator->errors()->add('cbm_in_container', __('The CBM in container can only be changed before the container is dispatched'));
        }
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): StockDeliveryItem
    {
        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, $request);

        return $this->handle($stockDeliveryItem, $this->validatedData);
    }

    public function action(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $this->asAction          = true;
        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, $modelData);

        return $this->handle($stockDeliveryItem, $this->validatedData);
    }

    public function jsonResponse(StockDeliveryItem $stockDeliveryItem): StockDeliveryItemResource
    {
        return new StockDeliveryItemResource($stockDeliveryItem);
    }
}

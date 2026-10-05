<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateFromItems;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateStockDeliveryItem extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        if (Arr::has($modelData, 'net_amount')) {
            data_set($modelData, 'grp_net_amount', Arr::get($modelData, 'net_amount') * ($stockDeliveryItem->grp_exchange ?? 1));
            data_set($modelData, 'org_net_amount', Arr::get($modelData, 'net_amount') * ($stockDeliveryItem->org_exchange ?? 1));
        }

        $stockDeliveryItem = $this->update($stockDeliveryItem, $modelData, ['data']);

        StockDeliveriesHydrateItems::dispatch($stockDeliveryItem->stockDelivery)->delay($this->hydratorsDelay);
        UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem::run($stockDeliveryItem);
        UpdateStockDeliveryStateFromItems::run($stockDeliveryItem->stockDelivery);

        return $stockDeliveryItem;
    }

    public function rules(): array
    {
        $rules = [
            'unit_quantity' => ['sometimes', 'required', 'numeric', 'gte:0'],
        ];

        if (!$this->strict) {
            $rules['state'] = ['sometimes','required', Rule::enum(StockDeliveryItemStateEnum::class)];
            $rules['unit_quantity_checked'] = ['sometimes', 'numeric', 'gte:0'];
            $rules['unit_quantity_placed'] = ['sometimes', 'numeric', 'gte:0'];
            $rules['net_amount'] = ['sometimes', 'numeric'];
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(StockDeliveryItem $stockDeliveryItem, array $modelData, int $hydratorsDelay = 0, bool $strict = true): StockDeliveryItem
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($stockDeliveryItem->organisation, $modelData);

        return $this->handle($stockDeliveryItem, $this->validatedData);
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): StockDeliveryItem
    {
        if ($stockDeliveryItem->stockDelivery->isManagedByPartner()) {
            throw ValidationException::withMessages(['state' => __('This delivery is managed by the partner until you receive it')]);
        }

        $this->initialisation($stockDeliveryItem->organisation, $request);

        return $this->handle($stockDeliveryItem, $this->validatedData);
    }

    public function jsonResponse(StockDeliveryItem $stockDeliveryItem): StockDeliveryItemResource
    {
        return new StockDeliveryItemResource($stockDeliveryItem);
    }
}

<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateStockDeliveryItem extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        return $this->update($stockDeliveryItem, $modelData, ['data']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
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
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(StockDeliveryItem $stockDeliveryItem, array $modelData, int $hydratorsDelay = 0, bool $strict = true): StockDeliveryItem
    {

        $this->asAction      = true;
        $this->strict        = $strict;

        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($stockDeliveryItem->organisation, $modelData);

        return $this->handle($stockDeliveryItem, $this->validatedData);
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): StockDeliveryItem
    {
        $this->initialisation($stockDeliveryItem->organisation, $request);
        return $this->handle($stockDeliveryItem, $this->validatedData);
    }

    public function jsonResponse(StockDeliveryItem $stockDeliveryItem): StockDeliveryItemResource
    {
        return new StockDeliveryItemResource($stockDeliveryItem);
    }
}

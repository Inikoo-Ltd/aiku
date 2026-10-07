<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 28 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\Traits\Authorisations\WithStockDeliveryCostingEditAuthorisation;
use App\Actions\GoodsIn\StockDelivery\EvaluateStockDeliveryCosting;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateCosts;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Http\Resources\Procurement\StockDeliveryItemCostResource;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateStockDeliveryItemCost extends OrgAction
{
    use WithStockDeliveryCostingEditAuthorisation;
    use WithActionUpdate;

    private const COST_FIELDS = [
        'cost_items',
        'cost_extra',
        'cost_shipping',
        'cost_duties',
        'cost_tax',
    ];

    private StockDeliveryItem $stockDeliveryItem;

    public function rules(): array
    {
        return collect(self::COST_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => ['sometimes', 'nullable', 'numeric', 'gte:0']])
            ->all();
    }

    public function afterValidator(Validator $validator): void
    {
        $stockDelivery = $this->stockDeliveryItem->stockDelivery;

        if ($stockDelivery->state !== StockDeliveryStateEnum::PLACED || $stockDelivery->is_costed) {
            $validator->errors()->add('state', __('You can only edit the costs while the costing is in progress'));
        }
    }

    public function handle(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $costs = [];

        foreach (self::COST_FIELDS as $field) {
            $costs[$field] = array_key_exists($field, $modelData)
                ? $modelData[$field]
                : $stockDeliveryItem->{$field};
        }

        $costs['cost_tax']   = $costs['cost_tax'] ?? 0;
        $costs['cost_total'] = array_sum(array_map(fn ($value) => (float) $value, $costs));

        $handSplitFields = array_filter(
            ['cost_extra', 'cost_shipping', 'cost_duties'],
            fn (string $field) => array_key_exists($field, $modelData) && EvaluateStockDeliveryCosting::cents((float) $modelData[$field]) !== EvaluateStockDeliveryCosting::cents((float) $stockDeliveryItem->{$field})
        );

        $stockDeliveryItem = $this->update($stockDeliveryItem, $costs);

        if ($handSplitFields) {
            $stockDelivery = $stockDeliveryItem->stockDelivery;
            $amounts       = EvaluateStockDeliveryCosting::splitAmounts($stockDelivery->costs()->get());
            $handSplit     = Arr::get($stockDelivery->data, 'costing_hand_split', []);
            foreach ($handSplitFields as $field) {
                $handSplit[$field] = EvaluateStockDeliveryCosting::cents($amounts[$field]);
            }
            $stockDelivery->update(['data' => array_merge($stockDelivery->data, ['costing_hand_split' => $handSplit])]);
        }

        StockDeliveriesHydrateCosts::run($stockDeliveryItem->stockDelivery);

        return $stockDeliveryItem->refresh();
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

    public function jsonResponse(StockDeliveryItem $stockDeliveryItem): StockDeliveryItemCostResource
    {
        return new StockDeliveryItemCostResource($stockDeliveryItem);
    }
}

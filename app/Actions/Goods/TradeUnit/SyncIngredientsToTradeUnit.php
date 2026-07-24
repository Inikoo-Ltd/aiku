<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateMarketingIngredients;
use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Models\Goods\Ingredient;
use App\Models\Goods\TradeUnit;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SyncIngredientsToTradeUnit extends GrpAction
{
    use WithGoodsEditAuthorisation;

    public function handle(TradeUnit $tradeUnit, array $modelData): TradeUnit
    {
        $slugs = array_values(Arr::get($modelData, 'ingredients') ?? []);

        $ingredientIds = Ingredient::where('group_id', $tradeUnit->group_id)
            ->whereIn('slug', $slugs)
            ->pluck('id', 'slug');

        $syncData = [];
        foreach ($slugs as $index => $slug) {
            $ingredientId = $ingredientIds->get($slug);
            if ($ingredientId) {
                $syncData[$ingredientId] = ['position' => $index + 1];
            }
        }

        $tradeUnit->ingredients()->sync($syncData);
        $tradeUnit->refresh();

        TradeUnitsHydrateMarketingIngredients::run($tradeUnit);

        return $tradeUnit->refresh();
    }

    public function rules(): array
    {
        return [
            'ingredients'   => ['present', 'array'],
            'ingredients.*' => [
                'string',
                Rule::exists('ingredients', 'slug')->where('group_id', $this->group->id)
            ],
        ];
    }

    public function action(TradeUnit $tradeUnit, array $modelData, int $hydratorsDelay = 0, bool $strict = true): TradeUnit
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($tradeUnit->group, $modelData);

        return $this->handle($tradeUnit, $this->validatedData);
    }
}

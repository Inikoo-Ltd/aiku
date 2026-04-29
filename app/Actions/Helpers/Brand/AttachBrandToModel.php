<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Brand;

use App\Actions\Helpers\Brand\Hydrators\BrandHydrateProducts;
use App\Actions\Helpers\Brand\Hydrators\BrandHydrateTradeUnits;
use App\Actions\OrgAction;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use App\Models\Helpers\Brand;
use Lorisleiva\Actions\ActionRequest;

class AttachBrandToModel extends OrgAction
{
    protected TradeUnit|TradeUnitFamily $parent;

    public function handle(TradeUnit|TradeUnitFamily $model, array $modelData): void
    {
        $previousBrandIds = $model->brands()->pluck('brands.id')->toArray();

        $model->brands()->sync($modelData['brand_id']);
        $model->refresh();

        $affectedBrandIds = array_unique(array_filter(array_merge(
            $previousBrandIds,
            $modelData['brand_id'] ? [$modelData['brand_id']] : []
        )));

        foreach ($affectedBrandIds as $brandId) {
            $brand = Brand::find($brandId);
            if ($brand) {
                BrandHydrateTradeUnits::dispatch($brand)->delay($this->hydratorsDelay);
                BrandHydrateProducts::dispatch($brand)->delay($this->hydratorsDelay);
            }
        }
    }

    public function rules(): array
    {
        return [
            'brand_id' => [
                'sometimes', 'nullable',
                'exists:brands,id',
            ],
        ];
    }

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request)
    {
        $this->parent = $tradeUnit;
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData);
    }

    public function action(TradeUnit|TradeUnitFamily $parent, array $modelData)
    {
        $this->parent = $parent;
        $this->initialisationFromGroup($parent->group, $modelData);

        $this->handle($parent, $this->validatedData);
    }
}

<?php

namespace App\Actions\Helpers\Brand;

use App\Actions\GrpAction;
use App\Actions\Helpers\Brand\Hydrators\BrandHydrateProducts;
use App\Actions\Helpers\Brand\Hydrators\BrandHydrateTradeUnits;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Brand;
use Lorisleiva\Actions\ActionRequest;

class AttachBrandToMultipleModel extends GrpAction
{
    public function handle(Brand $brand, array $modelData): void
    {
        $tradeUnits = TradeUnit::whereIn('id', data_get($modelData, 'trade_units.*.id', []))->get();

        $affectedBrandIds = [$brand->id];

        foreach ($tradeUnits as $tradeUnit) {
            $previousBrandIds = $tradeUnit->brands()->pluck('brands.id')->toArray();

            $tradeUnit->brands()->sync($brand->id);
            $tradeUnit->refresh();

            $affectedBrandIds = array_merge($affectedBrandIds, $previousBrandIds);
        }


        $brands = Brand::whereIn('id', $affectedBrandIds)->get();

        foreach ($brands as $brand) {
            BrandHydrateTradeUnits::dispatch($brand);
            BrandHydrateProducts::dispatch($brand);
        }
    }

    public function rules(): array
    {
        return [
            'trade_units.*.id'  => ['sometimes', 'numeric']
        ];
    }

    public function asController(Brand $brand, ActionRequest $request): void
    {
        $this->initialisation($brand->group, $request);

        $this->handle($brand, $this->validatedData);
    }

}

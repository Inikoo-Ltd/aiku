<?php

namespace App\Actions\Goods\TradeUnitFamily\UI;

use App\Actions\Goods\TradeUnit\HydrateTradeUnitBrandTagsFromFamily;
use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\GrpAction;
use App\Actions\Helpers\Brand\AttachBrandToModel;
use App\Actions\Helpers\Tag\AttachTagsToModel;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class AssignBrandTagsToTradeUnitFamily extends GrpAction
{
    use WithActionUpdate;

    public function handle(TradeUnitFamily $tradeUnitFamily, array $modelData): void
    {
        if (Arr::has($modelData, 'tags')) {
            AttachTagsToModel::make()->action($tradeUnitFamily, [
                'tags_id' => Arr::pull($modelData, 'tags')
            ], true);
        }

        if (Arr::has($modelData, 'brands')) {
            AttachBrandToModel::make()->action($tradeUnitFamily, [
                'brand_id' => Arr::pull($modelData, 'brands')
            ]);
        }

        HydrateTradeUnitBrandTagsFromFamily::dispatch($tradeUnitFamily);
    }

    public function rules(): array
    {
        return [
            'brands'    => ['sometimes', 'numeric', 'nullable'],
            'tags'      => ['sometimes', 'array'],
            'tags.*'    => ['sometimes', 'numeric', 'nullable'],
        ];
    }

    public function asController(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): void
    {
        $this->initialisation(group(), $request);

        $this->handle($tradeUnitFamily, $this->validatedData);
    }


}

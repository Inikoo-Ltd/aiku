<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 May 2023 11:42:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnitFamily;

use App\Actions\GrpAction;
use App\Actions\Helpers\Brand\AttachBrandToModel;
use App\Actions\Helpers\Tag\AttachTagsToModel;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateTradeUnitFamily extends GrpAction
{
    use WithActionUpdate;

    public function handle(TradeUnitFamily $tradeUnitFamily, array $modelData): TradeUnitFamily
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

        $tradeUnitFamily = $this->update($tradeUnitFamily, $modelData);

        return $tradeUnitFamily;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['sometimes', 'string', 'max:255'],
            'description'           => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];
    }

    public function asController(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): TradeUnitFamily
    {
        $this->initialisation($tradeUnitFamily->group, $request);

        return $this->handle($tradeUnitFamily, $this->validatedData);
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 May 2023 11:42:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnitFamily;

use App\Actions\Catalogue\ProductCategory\LabelingGuide\StoreLabelingGuide;
use App\Actions\OrgAction;
use App\Actions\Helpers\Brand\AttachBrandToModel;
use App\Actions\Helpers\Tag\AttachTagsToModel;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateTradeUnitFamily extends OrgAction
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
        
        // Handle labeling_guide pdf file upload
        if (Arr::has($modelData, 'labeling_guide_file') && data_get($modelData, 'labeling_guide_file', null) instanceof \Illuminate\Http\UploadedFile) {
            StoreLabelingGuide::make()->action($tradeUnitFamily, Arr::only($modelData, 'labeling_guide_file'));
            Arr::forget($modelData, 'labeling_guide_file');
        }

        $tradeUnitFamily = $this->update($tradeUnitFamily, $modelData);

        return $tradeUnitFamily;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['sometimes', 'string', 'max:255'],
            'description'           => ['sometimes', 'nullable', 'string', 'max:1024'],
            'labeling_guide_file'   => ['sometimes', 'nullable', File::types(['pdf'])->max(64000)], // 64mb max, following server max (prod on php.ini max file size upload)
        ];
    }

    public function asController(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): TradeUnitFamily
    {
        $this->initialisationFromGroup($tradeUnitFamily->group, $request);

        return $this->handle($tradeUnitFamily, $this->validatedData);
    }
}

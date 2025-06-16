<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Brand;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\OrgAction;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Brand;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class StoreBrand extends OrgAction
{
    public function handle(Group|TradeUnit $parent, array $modelData): Brand
    {

        $image = Arr::pull($modelData, 'image', null);
        if ($parent instanceof Group) {
            data_set($modelData, 'group_id', $parent->id);
        } else {
            data_set($modelData, 'group_id', $parent->group_id);
        }

        $brand = Brand::create($modelData);
        if ($image) {
            $imageData = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
            ];
            $brand     = SaveModelImage::run(
                model: $brand,
                imageData: $imageData,
                scope: 'brand',
            );

        }
        AttachBrandToModel::make()->handle(
            $parent,
            [
                'brand_id' => $brand->id
            ],
        );

        return $brand;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:255'],
            'image'                    => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
        ];
    }

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData);
    }

}

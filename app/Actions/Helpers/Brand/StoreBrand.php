<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Brand;

use App\Actions\OrgAction;
use App\Actions\Traits\UI\WithLogo;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Brand;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class StoreBrand extends OrgAction
{
    use WithLogo;
    public function handle(Group|TradeUnit $parent, array $modelData): Brand
    {

        $brand = Brand::create($modelData);

        $image = Arr::pull($modelData, 'image', null);
        if ($image) {
            $brand = $this->processWebsiteLogo(['image' => $image ], $brand);
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

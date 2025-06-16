<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Brand;

use App\Actions\OrgAction;
use App\Actions\Traits\UI\WithLogo;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Brand;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateBrand extends OrgAction
{
    use WithLogo;
    public function handle(Brand $brand, array $modelData): Brand
    {
        $image = Arr::pull($modelData, 'image', null);
        if ($image) {
            $brand = $this->processWebsiteLogo(['image' => $image ], $brand);
        }
        $brand->update($modelData);
        return $brand;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image'                    => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
        ];
    }

    public function inTradeUnit(TradeUnit $tradeUnit, Brand $brand, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($brand, $this->validatedData);
    }


}

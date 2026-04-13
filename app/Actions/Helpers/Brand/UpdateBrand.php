<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Brand;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\OrgAction;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateBrand extends OrgAction
{
    public function handle(Brand $brand, array $modelData): Brand
    {
        $image = Arr::pull($modelData, 'image', null);
        if ($image) {
            $imageData = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
            ];
            $brand     = SaveModelImage::run(
                model: $brand,
                imageData: $imageData,
                scope: 'image',
            );
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

    public function htmlResponse(Brand $brand): RedirectResponse
    {
        return Redirect::route('grp.trade_units.brands.index');
    }

    public function asController(Brand $brand, ActionRequest $request): Brand
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($brand, $this->validatedData);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, Brand $brand, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($brand, $this->validatedData);
    }


}

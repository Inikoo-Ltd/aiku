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
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Brand;
use Lorisleiva\Actions\ActionRequest;

class UpdateBrand extends OrgAction
{
    public function handle(Brand $brand, array $modelData): Brand
    {
        $brand->update($modelData);
        return $brand;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function inTradeUnit(TradeUnit $tradeUnit, Brand $brand, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($brand, $this->validatedData);
    }


}

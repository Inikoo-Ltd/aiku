<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\OrgAction;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Brand;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\ActionRequest;

class StoreBrand extends OrgAction
{
    public function handle(Group|TradeUnit $parent, array $modelData): Tag
    {

        $brand = Brand::create($modelData);

        AttachBrandToModel::make()->handle(
            $parent,
            [
                'brand_id' => [$brand->id]
            ],
        );

        return $brand;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:255'],
        ];
    }

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData);
    }

}

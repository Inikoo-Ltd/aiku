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
use Lorisleiva\Actions\ActionRequest;

class AttachBrandToModel extends OrgAction
{
    protected TradeUnit $parent;

    public function handle(TradeUnit $model, array $modelData): void
    {
        $model->brand()->attach($modelData['brand_id']);
        $model->refresh();
    }

    public function rules(): array
    {
        return [
            'brand_id' => [
                'required',
                'exists:brands,id',
            ],
        ];
    }

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request)
    {
        $this->parent = $tradeUnit;
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData);
    }
}

<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\OrgAction;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use Lorisleiva\Actions\ActionRequest;

class UpdateTag extends OrgAction
{
    public function handle(Tag $tag, array $modelData): Tag
    {
        $tag->update($modelData);
        return $tag;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function inTradeUnit(TradeUnit $tradeUnit, Tag $tag, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tag, $this->validatedData);
    }


}

<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Tag;

use App\Actions\OrgAction;
use App\Models\Catalogue\Tag;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\ActionRequest;

class DetachTagFromModel extends OrgAction
{
    public function handle(TradeUnit $model, Tag $tag): void
    {
        $model->tags()->detach([$tag->id]);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, Tag $tag, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $tag);
    }
}

<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\Helpers\Tag\Hydrators\TagHydrateModels;
use App\Actions\OrgAction;
use App\Models\CRM\Customer;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use Lorisleiva\Actions\ActionRequest;

class DetachTagFromModel extends OrgAction
{
    public function inTradeUnit(TradeUnit $tradeUnit, Tag $tag, ActionRequest $request): void
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $tag);
    }

    public function inCustomer(Customer $customer, Tag $tag, ActionRequest $request): void
    {
        $this->initialisation($customer->organisation, $request);

        $this->handle($customer, $tag);
    }

    public function handle(Customer|TradeUnit $model, Tag $tag): void
    {
        $model->tags()->detach([$tag->id]);

        $tag->refresh();

        TagHydrateModels::dispatch($tag);
    }
}

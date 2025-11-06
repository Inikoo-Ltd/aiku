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
use App\Models\CRM\Customer;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class DeleteTag extends OrgAction
{
    public function inTradeUnit(TradeUnit $tradeUnit, Tag $tag, ActionRequest $request): void
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tag);
    }

    public function inCustomer(Customer $customer, Tag $tag, ActionRequest $request): void
    {
        $this->initialisation($customer->organisation, $request);

        $this->handle($tag);
    }

    public function asController(Organisation $organisation, Tag $tag, ActionRequest $request): Tag
    {
        $this->initialisation($organisation, $request);

        return $this->handle($tag);
    }

    public function htmlResponse(): void
    {
        request()->session()->flash('notification', [
            'status'  => 'success',
            'title'   => __('Success!'),
            'description' => __('Tag successfully deleted.'),
        ]);
    }

    public function handle(Tag $tag): Tag
    {
        if (!empty($tag->tradeUnits())) {
            $tag->tradeUnits()->detach();
        }

        if (!empty($tag->customers())) {
            $tag->customers()->detach();
        }

        $tag->delete();

        return $tag;
    }
}

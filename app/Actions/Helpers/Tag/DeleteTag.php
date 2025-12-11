<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\OrgAction;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
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
        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($tag);
    }

    public function inSelfFilledTags(Organisation $organisation, Shop $shop, Tag $tag, ActionRequest $request): RedirectResponse
    {
        try {
            $this->initialisationFromShop($shop, $request);

            $this->handle($tag);

            return Redirect::route('grp.org.shops.show.crm.self_filled_tags.index', [
                $this->organisation->slug,
                $this->shop->slug
            ])->with('notification', [
                'status'  => 'success',
                'title'   => __('Success!'),
                'description' => __('Tag deleted.'),
            ]);
        } catch (Exception $e) {
            return Redirect::route('grp.org.shops.show.crm.self_filled_tags.index', [
                $this->organisation->slug,
                $this->shop->slug
            ])->with('notification', [
                'status'  => 'error',
                'title'   => __('Error!'),
                'description' => $e->getMessage(),
            ]);
        }
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

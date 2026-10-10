<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sep 2026 05:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithShopDashboardAuthorisation;
use App\Enums\Dashboards\ShopDashboardSectionsEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class ShowShopSalesAnalysis extends OrgAction
{
    use WithShopDashboardAuthorisation;
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        return redirect()->route('grp.org.shops.show.dashboard.show', array_merge(
            [$organisation->slug, $shop->slug, 'section' => ShopDashboardSectionsEnum::SALES_ANALYSIS->value],
            $request->only(['from', 'to', 'compareFrom', 'compareTo', 'partners'])
        ));
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Jul 2025 10:41:39 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Orders;

use App\Actions\OrgAction;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\ActionRequest;

class CallbackFetchWooUserOrders extends OrgAction
{
    public function handle(WooCommerceUser $wooCommerceUser): void
    {
        FetchWooUserOrders::dispatch($wooCommerceUser);
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request): void
    {
        $this->initialisation($wooCommerceUser->organisation, $request);

        $this->handle($wooCommerceUser);
    }
}

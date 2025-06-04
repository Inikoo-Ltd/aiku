<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\UI\Dispatch;

use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubShowcase
{
    use AsObject;

    public function handle(Warehouse $warehouse, ActionRequest $request): array
    {
        $organisationCatalogueStats = $warehouse->organisation->catalogueStats;

        $stats = [];

        if ($organisationCatalogueStats->number_current_shops_type_fulfilment) {
            $stats['fulfilment'] = GetDispatchHubFulfilmentWidget::run($warehouse, $request);
        }

        if ($organisationCatalogueStats->number_current_shops_type_b2b) {
            $stats['b2b'] = GetDispatchHubB2BWidget::run($warehouse, $request);
        }

        if ($organisationCatalogueStats->number_current_shops_type_b2c) {
            $stats['b2c'] = GetDispatchHubB2CWidget::run($warehouse);
        }

        if ($organisationCatalogueStats->number_current_shops_type_dropshipping) {
            $stats['dropshipping'] = GetDispatchHubDropshippingWidget::run($warehouse);
        }

        return $stats;
    }
}

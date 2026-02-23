<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 09 Feb 2022 15:04:15 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoiceIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSalesIntervals;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervals;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;

class HydrateShopsSales
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:shops_sales {organisations?*} {--s|slug=}';

    public function __construct()
    {
        $this->model = Shop::class;
    }

    public function handle(Shop $shop): void
    {
        ShopHydrateSalesIntervals::run($shop);
        ShopHydrateInvoiceIntervals::run($shop);
        ShopHydrateOrderIntervals::run($shop);
        ShopHydrateOrderInBasketAtCreatedIntervals::run($shop);
        ShopHydrateOrderInBasketAtCustomerUpdateIntervals::run($shop);

        if ($shop->type == ShopTypeEnum::DROPSHIPPING) {
            ShopHydratePlatformSalesIntervals::run($shop);
        }
    }

}

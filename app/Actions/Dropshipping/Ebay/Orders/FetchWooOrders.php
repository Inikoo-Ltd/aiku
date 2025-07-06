<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Jul 2025 10:39:37 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Actions\Dropshipping\WooCommerce\Orders\FetchWooUserOrders;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\Concerns\AsCommand;

class FetchWooOrders extends RetinaAction
{
    use AsCommand;

    public string $commandSignature = 'fetch:woo-orders';

    public function asCommand(): void
    {
        $this->handle();
    }

    public function handle(): void
    {
        $wooUsers = WooCommerceUser::whereNotNull('customer_sales_channel_id')->get();
        foreach ($wooUsers as $wooUser) {
            FetchWooUserOrders::run($wooUser);
        }
    }
}

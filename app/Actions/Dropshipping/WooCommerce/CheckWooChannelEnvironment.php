<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckWooChannelEnvironment
{
    use asAction;
    use WithActionUpdate;



    public function getCommandSignature(): string
    {
        return 'woo:check_basic {customerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $wooCommerceUser = $customerSalesChannel->user;
        $result = $wooCommerceUser->checkConnection();

        print_r($result);


    }
}

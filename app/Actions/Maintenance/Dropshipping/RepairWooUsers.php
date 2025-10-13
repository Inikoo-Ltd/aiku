<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\WooCommerce\CheckWooChannel;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairWooUsers
{
    use AsAction;
    use WithActionUpdate;

    public function handle(WooCommerceUser $wooCommerceUser): void
    {
        CheckWooChannel::run($wooCommerceUser);
    }

    public function getCommandSignature(): string
    {
        return 'repair:woo_users {slug?}';
    }

    public function asCommand(Command $command): void
    {
        if ($command->argument('slug')) {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('slug'))->first();
            $this->handle($customerSalesChannel->user);
        } else {
            $tableData = [];

            foreach (WooCommerceUser::all() as $wooUser) {
                $this->handle($wooUser);
            }

            $command->table(
                ['#', 'Woo Name', 'Channel', 'State'],
                array_map(function ($item) {
                    return [$item['counter'], $item['name'], $item['channel'], $item['state']];
                }, $tableData)
            );
        }
    }
}

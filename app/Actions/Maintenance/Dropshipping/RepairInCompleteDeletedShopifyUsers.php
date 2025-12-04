<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\Dropshipping\ShopifyUser\DeleteShopifyUser;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairInCompleteDeletedShopifyUsers
{
    use AsAction;
    use WithActionUpdate;
    use WithShopifyApi;

    public function handle(ShopifyUser $shopifyUser)
    {
        DeleteShopifyUser::run($shopifyUser);
    }

    public function getCommandSignature(): string
    {
        return 'repair:InCompleteDeletedShopifyUsers';
    }

    public function asCommand(Command $command): void
    {
        $shopifyUsers = ShopifyUser::whereHas('customerSalesChannel', function ($query) {
            $query->whereNotNull('closed_at');
        })->get();

        foreach ($shopifyUsers as $shopifyUser) {
            $this->handle($shopifyUser);
        }

    }

    public function asController(ActionRequest $request): void
    {
        $shopifyUsers = ShopifyUser::whereHas('customerSalesChannel', function ($query) {
            $query->whereNotNull('closed_at');
        })->get();

        foreach ($shopifyUsers as $shopifyUser) {
            $this->handle($shopifyUser);
        }
    }

}

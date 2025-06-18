<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 15:29:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\CRM;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\DB;

class RepairCustomerStatus
{
    use WithActionUpdate;


    protected function handle(Shop $shop): void
    {
        DB::table('customers')->where('shop_id', $shop->id)->where('status', CustomerStatusEnum::PENDING_APPROVAL)->update(['status' => CustomerStatusEnum::APPROVED->value]);
    }

    public string $commandSignature = 'customers:repair_status';

    public function asCommand(): void
    {
        $shops = Shop::where('registration_needs_approval', true)->get();

        foreach ($shops as $shop) {
            $this->handle($shop);
        }
    }

}

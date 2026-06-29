<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jun 2026 11:18:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateClv;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateCustomersClv
{
    use asAction;

    public function handle(?Command $command = null): void
    {
        $shopsIds = Shop::where('is_aiku', true)->where('state', ShopStateEnum::OPEN)->pluck('id')->toArray();
        DB::table('customers')->select('id', 'last_invoiced_at')->whereNotNull('last_invoiced_at')->whereIn('shop_id', $shopsIds)->whereNull('deleted_at')
            ->chunkById(
                100,
                function ($customers) use ($command) {
                    foreach ($customers as $customer) {
                        $lastInvoiced = Carbon::parse($customer->last_invoiced_at);

                        if ($lastInvoiced->diffInYears(now()) <= 1) {
                            CustomerHydrateClv::dispatch($customer->id)->delay(rand(1, 900));
                        } elseif (rand(1, 10) === 1) {
                            CustomerHydrateClv::dispatch($customer->id)->onQueue('hydrators-slave')->delay(rand(900, 3600));
                        }
                    }
                }
            );
    }

    public function getCommandSignature(): string
    {
        return 'customers:clv';
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }

}
<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Feb 2026 12:28:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\CRM;

use App\Actions\CRM\Customer\UpdateCustomerLastInvoicedDate;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class RepairCustomerLastInvoicedDate
{
    use AsAction;

    public function handle(Customer $customer): void
    {
        UpdateCustomerLastInvoicedDate::run($customer);
    }

    public function getCommandSignature(): string
    {
        return 'repair:customer:update-last-invoiced-date {shop?}';
    }

    public function asCommand(Command $command): void
    {
        if ($command->argument('shop')) {
            $shop     = Shop::where('slug', $command->argument('shop'))->firstOrFail();
            $shopsIds = [$shop->id];
        } else {
            $shopsIds = Shop::where('is_aiku', true)->pluck('id')->toArray();
        }

        $totalCount = Customer::whereIn('shop_id', $shopsIds)->count();

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat('aiku_eta');
        $bar->start();

        Customer::whereIn('shop_id', $shopsIds)->chunk(100, function ($customers) use ($bar) {
            foreach ($customers as $customer) {
                $this->handle($customer);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
    }

}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Mar 2026 13:39:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\CRM;

use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class RepairCustomerNotes
{
    use AsAction;

    public function handle(Customer $customer): void
    {
        if ($customer->warehouse_public_notes == '' || $customer->warehouse_public_notes == null || !$customer->warehouse_public_notes) {
            return;
        }

        if ($customer->warehouse_public_notes == $customer->warehouse_internal_notes) {
            $customer->update([
                'warehouse_public_notes' => null
            ]);

            return;
        }

        $internalNotes = $customer->warehouse_internal_notes;
        if ($internalNotes != '') {
            $internalNotes .= '; '.$customer->warehouse_public_notes;
        } else {
            $internalNotes = $customer->warehouse_public_notes;
        }

        $customer->update([
            'warehouse_public_notes'   => null,
            'warehouse_internal_notes' => $internalNotes,
        ]);
    }

    public function getCommandSignature(): string
    {
        return 'repair:customer:notes {shop?}';
    }

    public function asCommand(Command $command): void
    {
        if ($command->argument('shop')) {
            $shop     = Shop::where('slug', $command->argument('shop'))->firstOrFail();
            $shopsIds = [$shop->id];
        } else {
            $shopsIds = Shop::where('is_aiku', true)->pluck('id')->toArray();
        }

        $totalCount = Customer::whereIn('shop_id', $shopsIds)
            ->whereNotNull('warehouse_public_notes')
            ->where('warehouse_public_notes', '!=', '')
            ->count();

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat('aiku_eta');
        $bar->start();

        Customer::whereIn('shop_id', $shopsIds)
            ->whereNotNull('warehouse_public_notes')
            ->where('warehouse_public_notes', '!=', '')
            ->chunk(100, function ($customers) use ($bar) {
                foreach ($customers as $customer) {
                    $this->handle($customer);
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine();
    }

}

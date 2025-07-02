<?php
/*
 * author Arya Permana - Kirin
 * created on 02-07-2025-18h-41m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RepairManualSalesChannelMissingName
{
    use WithActionUpdate;


    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        if ($customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL) {
            $customerName = $customerSalesChannel->customer->name;
            if(!$customerSalesChannel->name) {
                DB::table('customer_sales_channels')->where('id', $customerSalesChannel->id)->update([
                    'name' => $customerName
                ]);
            }
        }
    }
    public string $commandSignature = 'repair:customer_sales_channel_name {customerSalesChannel?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('customerSalesChannel')) {
            $customerSalesChannel = CustomerSalesChannel::find($command->argument('customerSalesChannel'));
            $this->handle($customerSalesChannel);
            
        } else {
            $count = CustomerSalesChannel::whereNull('name')->count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            CustomerSalesChannel::orderBy('id')->whereNull('name')
                ->chunk(100, function (Collection $models) use ($bar) {
                    foreach ($models as $model) {
                        $this->handle($model);
                        $bar->advance();
                    }
                });
        }
    }

}

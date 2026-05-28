<?php

namespace App\Actions\Dropshipping\CustomerSalesChannel\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerSalesChannelsHydrateFulfilmentOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $commandSignature = 'hydrate:customer_sales_channel_fulfilment_orders {customerSalesChannel}';

    public function getJobUniqueId(CustomerSalesChannel $customerSalesChannel): string
    {
        return "{$customerSalesChannel->id}-hydrate-fulfilment-orders";
    }

    public function asCommand(Command $command): int
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        if (!$customerSalesChannel) {
            $command->error("Customer sales channel not found: {$command->argument('customerSalesChannel')}");

            return 1;
        }

        $this->handle($customerSalesChannel);

        $command->info("Fulfilment orders hydrated for customer sales channel: {$customerSalesChannel->slug}");

        return 0;
    }

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        $stats = [
            'number_fulfilment_orders' => PalletReturn::where('customer_sales_channel_id', $customerSalesChannel->id)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'pallet_returns',
                field: 'state',
                enum: PalletReturnStateEnum::class,
                models: PalletReturn::class,
                where: function ($q) use ($customerSalesChannel) {
                    $q->where('customer_sales_channel_id', $customerSalesChannel->id);
                },
                modelCustomLabel: 'fulfilment_orders'
            )
        );

        $customerSalesChannel->update($stats);
    }
}

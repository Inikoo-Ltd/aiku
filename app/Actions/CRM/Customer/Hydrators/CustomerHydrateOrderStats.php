<?php

namespace App\Actions\CRM\Customer\Hydrators;

use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateOrderStats implements ShouldBeUnique
{
    use AsAction;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // Update signature agar sesuai dengan nama baru
    public string $commandSignature = 'hydrate:all-customers-order-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    public string $commandDescription = 'Hydrate order related stats (sales channels, collection, etc) for all customers';

    public function getJobUniqueId(int|null $customerId): string
    {
        return $customerId ?? 'empty';
    }

    public function handle(int|null $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        $customer = Customer::find($customerId);

        if (! $customer) {
            return;
        }

        $stats = [
            'number_orders_sales_channel_type_showroom' => $customer->orders()
                ->whereHas('salesChannel', function ($q) {
                    $q->where('type', SalesChannelTypeEnum::SHOWROOM);
                })->count(),

            'number_orders_sales_channel_type_phone' => $customer->orders()
                ->whereHas('salesChannel', function ($q) {
                    $q->where('type', SalesChannelTypeEnum::PHONE);
                })->count(),

            'number_orders_sales_channel_type_email' => $customer->orders()
                ->whereHas('salesChannel', function ($q) {
                    $q->where('type', SalesChannelTypeEnum::EMAIL);
                })->count(),

            'number_orders_sales_channel_type_website' => $customer->orders()
                ->whereHas('salesChannel', function ($q) {
                    $q->where('type', SalesChannelTypeEnum::WEBSITE);
                })->count(),

            'number_orders_sales_channel_type_other' => $customer->orders()
                ->whereHas('salesChannel', function ($q) {
                    $q->where('type', SalesChannelTypeEnum::OTHER);
                })->count(),

            'number_orders_with_collection_address' => $customer->orders()
                ->whereNotNull('collection_address_id')
                ->count(),
        ];

        $customer->stats()->update($stats);
    }

    public function asCommand(Command $command): void
    {
        $command->info('Starting hydration of customer order stats...');
        $totalCustomers = Customer::count();
        $bar = $command->getOutput()->createProgressBar($totalCustomers);
        $bar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        Customer::chunkById(1000, function ($customers) use ($bar) {
            foreach ($customers as $customer) {
                $this->handle($customer->id);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
        $command->info('All customers hydrated successfully!');
    }
}

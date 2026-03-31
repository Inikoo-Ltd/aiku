<?php

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\CRM\CustomerInterest\RecordCustomerProductInterests;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateProductInterests implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:customer-product-interests {customer}';

    public function getJobUniqueId(int|null $customerId): string
    {
        return $customerId ?? 'empty';
    }

    public function asCommand(Command $command): void
    {
        $customer = Customer::where('slug', $command->argument('customer'))->first();

        if (!$customer) {
            $command->error('Customer not found.');

            return;
        }

        $this->handle($customer->id);
        $command->info("Product interests hydrated for customer: {$customer->slug}");
    }

    public function handle(int|null $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        RecordCustomerProductInterests::run($customer);
    }
}

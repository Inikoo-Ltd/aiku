<?php

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class CustomerHydrateBundles implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:customer-bundles {organisations?*} {--S|shop= shop slug} {--s|slug=}';

    public function __construct()
    {
        $this->model            = Customer::class;
        $this->modelAsHandleArg = false;
    }

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

        if (!$customer) {
            return;
        }

        $stats = [
            'number_bundles'         => DB::table('portfolios')->where('customer_id', $customer->id)->where('is_bundle', true)->count(),
            'number_current_bundles' => DB::table('portfolios')->where('customer_id', $customer->id)->where('is_bundle', true)->where('status', true)->count(),
        ];

        $customer->stats()->update($stats);
    }
}

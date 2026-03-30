<?php

namespace App\Actions\CRM\CustomerInterest;

use App\Actions\Web\WebsitePageView\GetCustomerProductInterests;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerInterest;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordCustomerProductInterests
{
    use AsAction;

    public function handle(Customer $customer, int $days = 90, int $limit = 10): CustomerInterest
    {
        $existing = collect($customer->interests?->top_products ?? [])
            ->keyBy('product_id');

        $fresh = GetCustomerProductInterests::run($customer, $days, $limit)
            ->keyBy('product_id');

        $merged = $this->merge($existing, $fresh, $limit);

        return CustomerInterest::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'group_id'                 => $customer->group_id,
                'organisation_id'          => $customer->organisation_id,
                'shop_id'                  => $customer->shop_id,
                'top_products'             => $merged,
                'top_products_computed_at' => now(),
            ]
        );
    }

    private function merge(Collection $existing, Collection $fresh, int $limit): array
    {
        $preservedFromHistory = $existing->filter(fn ($entry) => !$fresh->has($entry['product_id']));

        $updatedFromFresh = $fresh->map(fn ($item) => [
            'product_id'          => $item->product_id,
            'product_name'        => $item->product_name,
            'product_code'        => $item->product_code,
            'view_count'          => (int) $item->view_count,
            'add_to_basket_count' => (int) $item->add_to_basket_count,
            'last_viewed_at'      => $item->last_viewed_at,
        ]);

        return $updatedFromFresh
            ->union($preservedFromHistory)
            ->sortByDesc('view_count')
            ->values()
            ->take($limit)
            ->map(fn ($item, $index) => array_merge($item, ['rank' => $index + 1]))
            ->values()
            ->all();
    }
}

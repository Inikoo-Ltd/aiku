<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateExclusiveProducts;
use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sets which customers may buy a product. Passing an empty list makes the product public again.
 *
 * exclusive_for_customer_id is kept in step with the pivot: it stays the marker every existing
 * "is this exclusive" query reads, and holds the first customer of the list.
 */
class SyncProductExclusiveCustomers extends OrgAction
{
    use AsAction;

    public function handle(Product $product, array $customerIds): Product
    {
        $customerIds = array_values(array_unique(array_filter($customerIds)));

        $before = $product->exclusiveCustomers()->pluck('customers.id')->all();

        $product->exclusiveCustomers()->sync($customerIds);

        $product->updateQuietly([
            'exclusive_for_customer_id' => $customerIds[0] ?? null,
        ]);

        foreach (array_unique(array_merge($before, $customerIds)) as $customerId) {
            CustomerHydrateExclusiveProducts::dispatch($customerId);
        }

        return $product->refresh();
    }

    public function rules(): array
    {
        return [
            'customer_ids'   => ['present', 'array'],
            'customer_ids.*' => [
                'integer',
                Rule::exists('customers', 'id')->where('shop_id', $this->shop->id),
            ],
        ];
    }

    public function action(Product $product, array $modelData): Product
    {
        $this->asAction = true;
        $this->initialisationFromShop($product->shop, $modelData);

        return $this->handle($product, $this->validatedData['customer_ids']);
    }
}

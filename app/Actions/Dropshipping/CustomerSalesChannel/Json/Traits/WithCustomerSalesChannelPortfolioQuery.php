<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Nov 2025 16:16:56 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Junie (JetBrains AI) for Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Nov 2025 16:20:00 Central Indonesia Time
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel\Json\Traits;

use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Services\QueryBuilder;

/**
 * Shared helpers to resolve CustomerSalesChannel IDs that contain exactly a given set of product IDs.
 *
 * SQL semantics:
 * - Join portfolios on customer_sales_channels.id where portfolios.item_type = 'Product' and item_id in (:productIds)
 * - Group by customer_sales_channels.id
 * - Having COUNT(DISTINCT portfolios.item_id) = count(:productIds)
 *
 * Assumptions:
 * - Portfolios table holds product assignments to customer sales channels.
 * - We only consider Product item_type entries.
 */
trait WithCustomerSalesChannelPortfolioQuery
{
    /**
     * Build the list of CustomerSalesChannel IDs that include all provided product IDs.
     * Returns an empty array if the input set is empty.
     */
    protected function buildCustomerSalesChannelIdsForProductIds(Customer $customer, iterable $productIds): array
    {
        // Normalize to array and ensure scalar ints
        $ids = [];
        foreach ($productIds as $id) {
            if ($id !== null) {
                $ids[] = (int)$id;
            }
        }

        if (count($ids) === 0) {
            return [];
        }

        $queryBuilder = QueryBuilder::for(CustomerSalesChannel::class);
        $queryBuilder->where('customer_sales_channels.customer_id', $customer->id)
            ->distinct();

        $queryBuilder->join('portfolios', function ($join) use ($ids) {
            $join->on('customer_sales_channels.id', '=', 'portfolios.customer_sales_channel_id')
                ->where('portfolios.item_type', '=', 'Product')
                ->whereIn('portfolios.item_id', $ids);
        });

        $queryBuilder
            ->select('customer_sales_channels.id')
            ->groupBy('customer_sales_channels.id')
            ->havingRaw('COUNT(DISTINCT portfolios.item_id) = ?', [count($ids)]);

        return $queryBuilder->get()->pluck('id')->toArray();
    }

    /**
     * Helper to extract product IDs from a Collection.
     * Returns a plain array of ints.
     */
    protected function getProductIdsFromCollection(Collection $collection): array
    {
        return $collection->products()->pluck('id')->map(fn ($id) => (int)$id)->all();
    }

    /**
     * Helper to extract product IDs from a ProductCategory.
     * Returns a plain array of ints.
     */
    protected function getProductIdsFromProductCategory(ProductCategory $category): array
    {
        // getProducts() is the canonical way used elsewhere in the codebase
        return $category->getProducts()->pluck('id')->map(fn ($id) => (int)$id)->all();
    }
}

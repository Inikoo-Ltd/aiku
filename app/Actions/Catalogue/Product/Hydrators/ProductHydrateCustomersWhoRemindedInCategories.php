<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Oct 2024 16:37:54 Central Indonesia Time, Office, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateCustomersWhoRemindedInCategories implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        $categories = ['department', 'subDepartment', 'family'];

        foreach ($categories as $categoryType) {
            $productCategory = $product->{$categoryType};

            if ($productCategory) {
                $methodName = lcfirst($categoryType) . 'BackInStockReminders';

                if (method_exists($productCategory, $methodName)) {
                    $stats = [
                        'number_customers_who_reminded' => $productCategory->{$methodName}()->whereNull('un_reminded_at')->count(),
                        'number_customers_who_un_reminded' => $productCategory->{$methodName}()->whereNotNull('un_reminded_at')->count(),
                    ];

                    $productCategory->stats->update($stats);
                }
            }
        }
    }

}

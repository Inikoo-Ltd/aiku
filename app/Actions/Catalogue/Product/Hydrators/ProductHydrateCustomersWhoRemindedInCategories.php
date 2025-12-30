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
use App\Models\CRM\BackInStockReminderSnapshot;

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
                $columnName = match($categoryType) {
                    'family' => 'family_id',
                    'subDepartment' => 'sub_department_id',
                    'department' => 'department_id',
                };

                // update the stats for the product category
                $stats = [
                        'number_customers_who_reminded' => BackInStockReminderSnapshot::where($columnName, $productCategory->id)->whereNull('reminder_cancelled_at')->whereNotNull('reminder_sent_at')->count(),
                        'number_customers_who_un_reminded' => BackInStockReminderSnapshot::where($columnName, $productCategory->id)->whereNotNull('reminder_cancelled_at')->whereNull('reminder_sent_at')->count(),
                    ];
                $productCategory->stats->update($stats);
            }
        }
    }

}

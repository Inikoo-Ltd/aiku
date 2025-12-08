<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 17:12:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace Database\Seeders;

use App\Enums\Helpers\TaxCategories\TaxCategoryTypeEnum;
use App\Models\Helpers\Country;
use App\Models\Helpers\TaxCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class TaxCategorySeeder extends Seeder
{
    public function run(): void
    {
        $taxCategories = json_decode(Storage::disk('datasets')->get('tax-categories.json'));

        foreach ($taxCategories as $taxCategoryRawData) {

            $type = match ($taxCategoryRawData->{'Tax Category Type'}) {
                'Standard' => TaxCategoryTypeEnum::STANDARD,
                'Reduced' => TaxCategoryTypeEnum::REDUCED,
                'Outside' => TaxCategoryTypeEnum::OUTSIDE,
                'EU_VTC' => TaxCategoryTypeEnum::EU_VTC,
                'Special' => TaxCategoryTypeEnum::SPECIAL,
                'ReducedSpecial' => TaxCategoryTypeEnum::REDUCED_SPECIAL,
                'Exempt' => TaxCategoryTypeEnum::EXEMPT,
                default => TaxCategoryTypeEnum::LEGACY,
            };

            $taxCategoryData = [
                'label' => $taxCategoryRawData->{'Tax Category Code'},
                'name' => $taxCategoryRawData->{'Tax Category Name'},
                'status' => $taxCategoryRawData->{'Tax Category Active'} === 'Yes',
                'rate' => $taxCategoryRawData->{'Tax Category Rate'},
                'country_id' => Country::where('code', $taxCategoryRawData->{'Tax Category Country 2 Alpha Code'})->value('id'),
                'data' => $taxCategoryRawData->{'Tax Category Metadata'},
                'type' => $type,
                'type_name' => $taxCategoryRawData->{'Tax Category Type Name'},
                'source_id' => $taxCategoryRawData->{'Tax Category Key'},
            ];

            $taxCategory = TaxCategory::where('source_id', $taxCategoryRawData->{'Tax Category Key'})->first();

            if ($taxCategory) {
                $taxCategory->update($taxCategoryData);
            } else {
                TaxCategory::create($taxCategoryData);
            }

        }
    }
}

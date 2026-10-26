<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Ordering\Order\ResetOrderTaxCategory;
use App\Enums\Helpers\TaxCategories\TaxCategoryTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Helpers\Country;
use App\Models\Helpers\TaxCategory;
use App\Models\Masters\MasterAsset;
use App\Models\Ordering\Order;
use Illuminate\Database\Migrations\Migration;

/**
 * HELP-4321: the 2023 cut to 16% ended on 1 January 2024, Luxembourg is 17% again, and its
 * 3% super-reduced rate joins the Food preset. Food products are re-expanded through
 * UpdateMasterAsset so they mint historics like any preset change, then Luxembourg baskets
 * still sitting on 16% are re-rated.
 */
return new class () extends Migration {
    public function up(): void
    {
        $luxembourgId = Country::where('code', 'LU')->value('id');
        if (!$luxembourgId) {
            return;
        }

        TaxCategory::where('country_id', $luxembourgId)->where('label', 'LU-SR')->update(['status' => true]);
        $retiredStandardIds = TaxCategory::where('country_id', $luxembourgId)->where('label', 'LU-SR16')->pluck('id');
        TaxCategory::whereIn('id', $retiredStandardIds)->update(['status' => false]);

        if (!TaxCategory::where('country_id', $luxembourgId)->where('label', 'LU-RR')->exists()) {
            TaxCategory::create([
                'label'      => 'LU-RR',
                'name'       => 'TVA 3%',
                'status'     => true,
                'rate'       => 0.03,
                'country_id' => $luxembourgId,
                'type'       => TaxCategoryTypeEnum::REDUCED,
                'type_name'  => 'Super-reduced',
                'source_id'  => 104,
            ]);
        }

        MasterAsset::where('tax_preset', 'food')->each(
            fn (MasterAsset $masterAsset) => UpdateMasterAsset::make()->action($masterAsset, ['tax_preset' => 'food'], strict: false)
        );

        Order::whereIn('tax_category_id', $retiredStandardIds)
            ->where('state', OrderStateEnum::CREATING)
            ->each(fn (Order $order) => ResetOrderTaxCategory::run($order));
    }

    public function down(): void
    {
        $luxembourgId = Country::where('code', 'LU')->value('id');

        TaxCategory::where('country_id', $luxembourgId)->where('label', 'LU-SR16')->update(['status' => true]);
        TaxCategory::where('country_id', $luxembourgId)->whereIn('label', ['LU-SR', 'LU-RR'])->update(['status' => false]);
    }
};

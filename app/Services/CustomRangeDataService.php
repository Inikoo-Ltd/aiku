<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 26 Nov 2025 16:21:33 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Services;

use App\Actions\Accounting\InvoiceCategory\InvoiceCategoryCalculateCustomRangeSales;
use App\Actions\Catalogue\Shop\ShopCalculateCustomRangeSales;
use App\Actions\SysAdmin\Organisation\OrganisationCalculateCustomRangeSales;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;

class CustomRangeDataService
{
    public function getGroupCustomRangeData(Group $group, string $startDate, string $endDate): array
    {
        $data = [];

        foreach ($group->organisations as $organisation) {
            $data['organisations'][$organisation->id] = OrganisationCalculateCustomRangeSales::run($organisation, $startDate, $endDate);
        }

        foreach ($group->shops as $shop) {
            $data['shops'][$shop->id] = ShopCalculateCustomRangeSales::run($shop, $startDate, $endDate);
        }

        foreach ($group->invoiceCategories as $invoiceCategory) {
            $data['invoice_categories'][$invoiceCategory->id] = InvoiceCategoryCalculateCustomRangeSales::run($invoiceCategory, $startDate, $endDate);
        }

        return $data;
    }

    public function getOrganisationCustomRangeData(Organisation $organisation, string $startDate, string $endDate): array
    {
        $data = [];

        foreach ($organisation->shops as $shop) {
            $data['shops'][$shop->id] = ShopCalculateCustomRangeSales::run($shop, $startDate, $endDate);
        }

        foreach ($organisation->invoiceCategories as $invoiceCategory) {
            $data['invoice_categories'][$invoiceCategory->id] = InvoiceCategoryCalculateCustomRangeSales::run($invoiceCategory, $startDate, $endDate);
        }

        return $data;
    }

    public function injectCustomRangeData(Collection $models, array $customRangeData, string $modelType): Collection
    {
        return $models->map(function ($model) use ($customRangeData, $modelType) {
            $customData = $customRangeData[$modelType][$model->id] ?? [];

            foreach ($customData as $key => $value) {
                $model->{$key} = $value;
            }

            $model->has_custom_range = true;

            return $model;
        });
    }
}

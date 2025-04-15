<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 13:40:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Dashboards;

use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\UI\Dashboard\DashboardDataType;
use App\Models\Accounting\InvoiceCategoryOrderingIntervals;
use App\Models\Accounting\InvoiceCategorySalesIntervals;
use App\Models\Catalogue\ShopOrderingIntervals;
use App\Models\Catalogue\ShopSalesIntervals;
use App\Models\SysAdmin\GroupOrderingIntervals;
use App\Models\SysAdmin\GroupSalesIntervals;
use App\Models\SysAdmin\OrganisationOrderingIntervals;
use App\Models\SysAdmin\OrganisationSalesIntervals;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;

trait WithDashboardIntervalValues
{
    private function getIntervalValues(
        ShopOrderingIntervals|ShopSalesIntervals|InvoiceCategoryOrderingIntervals|InvoiceCategorySalesIntervals|OrganisationOrderingIntervals|OrganisationSalesIntervals|GroupOrderingIntervals|GroupSalesIntervals $intervalsModel,
        string $field,
        DashboardDataType $dataType,
        array $options = []
    ): array {
        return collect(DateIntervalEnum::cases())->mapWithKeys(function ($interval) use ($intervalsModel, $field, $dataType, $options) {
            $rawValue = $intervalsModel->{$field.'_'.$interval->value};


            $data = [
                'raw_value' => $rawValue,
                'tooltip'   => '',
            ];

            switch ($dataType) {
                case DashboardDataType::NUMBER_MINIFIED:
                    $data['formatted_value'] = Number::abbreviate($rawValue);
                    break;
                case DashboardDataType::CURRENCY:
                    if (is_null(Arr::get($options, 'currency'))) {
                        dd($intervalsModel, $field, $interval->value, $field.'_'.$interval->value, $options);
                    }
                    $data['formatted_value'] = Number::currency($rawValue, Arr::get($options, 'currency'));
                    break;
                case DashboardDataType::CURRENCY_MINIFIED:
                    $data['formatted_value'] = Number::abbreviateCurrency($rawValue);
                    break;
                case DashboardDataType::PERCENTAGE:
                    $data['formatted_value'] = Number::percentage($rawValue, Arr::get($options, 'percentage'));
                    break;
                case DashboardDataType::DELTA_LAST_YEAR:
                    $data['formatted_value'] = Number::delta($rawValue, $intervalsModel->{$field.'_'.$interval->value}.'_ly');
                    break;
                default: // as DashboardDataType::NUMBER:
                    if (is_null($rawValue)) {
                        dd($field, $interval->value, $intervalsModel);
                    }

                    $data['formatted_value'] = Number::format($rawValue);
                    break;
            }

            return [$interval->value => $data];
        })->toArray();
    }

    public function getDashboardTableColumn(
        ShopOrderingIntervals|ShopSalesIntervals|InvoiceCategoryOrderingIntervals|InvoiceCategorySalesIntervals|OrganisationOrderingIntervals|OrganisationSalesIntervals|GroupOrderingIntervals|GroupSalesIntervals $intervalsModel,
        string $columnFingerprint
    ): array {
        $originalColumnFingerprint = $columnFingerprint;


        $dataType = DashboardDataType::NUMBER;


        if (str_ends_with($columnFingerprint, '_minified')) {
            $columnFingerprint = substr($columnFingerprint, 0, -strlen('_minified'));
            $dataType          = DashboardDataType::NUMBER_MINIFIED;
        }


        if (str_ends_with($columnFingerprint, '_delta')) {
            $columnFingerprint = substr($columnFingerprint, 0, -strlen('_delta'));
            $dataType          = DashboardDataType::DELTA_LAST_YEAR;
        } elseif (str_ends_with($columnFingerprint, '_currency')) {
            $dataType = $dataType == DashboardDataType::NUMBER_MINIFIED ? DashboardDataType::CURRENCY_MINIFIED : DashboardDataType::CURRENCY;
        }


        $options = [];


        if (str_ends_with($columnFingerprint, '_shop_currency')) {
            $options['currency'] = $intervalsModel->currency->code;
            $columnFingerprint   = substr($columnFingerprint, 0, -strlen('_shop_currency'));
        } elseif (str_ends_with($columnFingerprint, '_invoice_category_currency')) {
            $options['currency'] = $intervalsModel->invoiceCategory->currency->code;
            $columnFingerprint   = substr($columnFingerprint, 0, -strlen('_invoice_category_currency'));
        } elseif (str_ends_with($columnFingerprint, '_org_currency')) {
            $options['currency'] = $intervalsModel->organisation->currency->code;
        } elseif (str_ends_with($columnFingerprint, '_grp_currency')) {
            $options['currency'] = $intervalsModel->group->currency->code;
        }


        return [
            $originalColumnFingerprint => $this->getIntervalValues(
                $intervalsModel,
                $columnFingerprint,
                $dataType,
                $options,
            )
        ];
    }

}

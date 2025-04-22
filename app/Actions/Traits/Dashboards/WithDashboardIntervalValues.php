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
        array $options = [],
        array $routeTarget = []
    ): array {
        return collect(DateIntervalEnum::cases())->mapWithKeys(function ($interval) use ($intervalsModel, $field, $dataType, $options, $routeTarget) {
            $rawValue = $intervalsModel->{$field.'_'.$interval->value} ?? 0;


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
                    $data['formatted_value'] = Number::abbreviateCurrency($rawValue, Arr::get($options, 'currency'));
                    break;
                case DashboardDataType::PERCENTAGE:
                    $data['formatted_value'] = Number::percentage($rawValue, Arr::get($options, 'percentage'));
                    break;
                case DashboardDataType::DELTA_LAST_YEAR:
                    $lyValue = ($interval->value != 'all' ? $intervalsModel->{$field.'_'.$interval->value.'_ly'} : $intervalsModel->{$field.'_'.$interval->value}) ?? 0;
                    $data['formatted_value'] = Number::delta($rawValue, $lyValue);
                    if (Arr::get($options, 'currency') && $interval->value != 'all') {
                        $data['tooltip'] = Number::currency($lyValue, Arr::get($options, 'currency'));
                    } else {
                        $data['tooltip'] = $lyValue;
                    }
                    $data['delta_icon'] = Number::deltaIcon($rawValue, $lyValue, Arr::get($options, 'inverse_delta', false));
                    break;
                default: // as DashboardDataType::NUMBER:
                    if (is_null($rawValue)) {
                        dd($field, $interval->value, $intervalsModel);
                    }

                    $data['formatted_value'] = Number::format($rawValue);
                    break;
            }

            $routeTargetData = Arr::get($routeTarget, 'route_target');
            $keyInterval = Arr::get($routeTarget, 'key_interval');

            if ($routeTargetData && $keyInterval) {
                $routeTargetData['parameters'] = array_merge(
                    $routeTargetData['parameters'] ?? [],
                    [
                        $keyInterval => $this->getDateIntervalFilter($interval->value),
                    ]
                );

                $data['route_target'] = $routeTargetData;
            }

            return [$interval->value => $data];
        })->toArray();
    }

    private function getDateIntervalFilter($interval): string
    {
        $intervals = [
            '1y' => now()->subYear(),
            '1q' => now()->subQuarter(),
            '1m' => now()->subMonth(),
            '1w' => now()->subWeek(),
            '3d' => now()->subDays(3),
            '1d' => now()->subDay(),
            'ytd' => now()->startOfYear(),
            'tdy' => now()->startOfDay(),
            'qtd' => now()->startOfQuarter(),
            'mtd' => now()->startOfMonth(),
            'wtd' => now()->startOfWeek(),
            'lm' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'lw' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'ld' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
        ];

        if (!isset($intervals[$interval])) {
            return '';
        }

        $start = is_array($intervals[$interval]) ? $intervals[$interval][0] : $intervals[$interval];
        $end = is_array($intervals[$interval]) ? $intervals[$interval][1] : now();

        return str_replace('-', '', $start->toDateString()) . '-' . str_replace('-', '', $end->toDateString());
    }

    public function getDashboardTableColumn(
        ShopOrderingIntervals|ShopSalesIntervals|InvoiceCategoryOrderingIntervals|InvoiceCategorySalesIntervals|OrganisationOrderingIntervals|OrganisationSalesIntervals|GroupOrderingIntervals|GroupSalesIntervals $intervalsModel,
        string $columnFingerprint,
        array $routeTarget = []
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
        } elseif (str_ends_with($columnFingerprint, '_inverse_delta')) {
            $options['inverse_delta'] = true;
        }


        return [
            $originalColumnFingerprint => $this->getIntervalValues(
                $intervalsModel,
                $columnFingerprint,
                $dataType,
                $options,
                $routeTarget
            )
        ];
    }

}

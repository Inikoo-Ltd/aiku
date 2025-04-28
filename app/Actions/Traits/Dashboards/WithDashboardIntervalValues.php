<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 13:40:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Dashboards;

use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\UI\Dashboard\DashboardDataType;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;

trait WithDashboardIntervalValues
{
    private function getIntervalValues(
        $intervalsModel,
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
                    $lyValue                 = ($interval->value != 'all' ? $intervalsModel->{$field.'_'.$interval->value.'_ly'} : $intervalsModel->{$field.'_'.$interval->value}) ?? 0;
                    $data['formatted_value'] = Number::delta($rawValue, $lyValue);
                    $data['raw_value']       = Number::rawDelta($rawValue, $lyValue);
                    if ($interval->value != 'all') {
                        if (Arr::get($options, 'currency')) {
                            $data['tooltip'] = Number::currency($lyValue, Arr::get($options, 'currency'));
                        } else {
                            $data['tooltip'] = Number::format($lyValue);
                        }
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
            if ($routeTargetData) {
                $data['route_target'] = $routeTargetData;
            }

            return [$interval->value => $data];
        })->toArray();
    }


    public function getDashboardTableColumn($intervalsModel, string $columnFingerprint, array $routeTarget = []): array
    {
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
            $shopCurrencyCode = $intervalsModel->shopCurrencyCode;
            if (!$shopCurrencyCode) {
                $shopCurrencyCode = $intervalsModel->shop->currency->code;
            }
            $options['currency'] = $shopCurrencyCode;
            $columnFingerprint   = substr($columnFingerprint, 0, -strlen('_shop_currency'));
        } elseif (str_ends_with($columnFingerprint, '_invoice_category_currency')) {
            $invoiceCategoryCurrencyCode = $intervalsModel->group_currency_code;
            if (!$invoiceCategoryCurrencyCode) {
                $invoiceCategoryCurrencyCode = $intervalsModel->invoiceCategory->currency->code;
            }

            $options['currency'] = $invoiceCategoryCurrencyCode;
            $columnFingerprint   = substr($columnFingerprint, 0, -strlen('_invoice_category_currency'));
        } elseif (str_ends_with($columnFingerprint, '_org_currency')) {
            $organisationCurrencyCode = $intervalsModel->organisationCurrencyCode;
            if (!$organisationCurrencyCode) {
                $organisationCurrencyCode = $intervalsModel->organisation->currency->code;
            }
            $options['currency'] = $organisationCurrencyCode;
        } elseif (str_ends_with($columnFingerprint, '_grp_currency')) {
            $groupCurrencyCode = $intervalsModel->group_currency_code;
            if (!$groupCurrencyCode) {
                $groupCurrencyCode = $intervalsModel->group->currency->code;
            }

            $options['currency'] = $groupCurrencyCode;
        } elseif (str_ends_with($columnFingerprint, '_inverse')) {
            $options['inverse_delta'] = true;
            $columnFingerprint        = substr($columnFingerprint, 0, -strlen('_inverse'));
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

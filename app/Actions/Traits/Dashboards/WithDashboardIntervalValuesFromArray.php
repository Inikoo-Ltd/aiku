<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Fri, 23 Jan 2026
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Traits\Dashboards;

use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\UI\Dashboard\DashboardDataType;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;

trait WithDashboardIntervalValuesFromArray
{
    /**
     * Get interval values from array data
     *
     * @param array $data The source array containing interval data
     * @param string $field The field name (e.g., 'sales', 'customers')
     * @param DashboardDataType $dataType The type of data formatting
     * @param array $options Additional options (currency, inverse_delta, etc.)
     * @param array $routeTarget Route configuration for links
     * @return array
     */
    private function getIntervalValuesFromArray(array $data, string $field, DashboardDataType $dataType, array $options = [], array $routeTarget = []): array
    {
        return collect(DateIntervalEnum::cases())->mapWithKeys(function ($interval) use ($data, $field, $dataType, $options, $routeTarget) {
            $key = $field . '_' . $interval->value;
            $rawValue = $data[$key] ?? 0;

            $result = [
                'raw_value' => $rawValue,
                'tooltip'   => '',
            ];

            switch ($dataType) {
                case DashboardDataType::NUMBER_MINIFIED:
                    $result['formatted_value'] = Number::abbreviate($rawValue);
                    break;

                case DashboardDataType::CURRENCY:
                    $currency = Arr::get($options, 'currency', 'GBP');
                    $result['formatted_value'] = Number::currency($rawValue, $currency);
                    break;

                case DashboardDataType::CURRENCY_MINIFIED:
                    $currency = Arr::get($options, 'currency', 'GBP');
                    $result['formatted_value'] = Number::abbreviateCurrency($rawValue, $currency);
                    break;

                case DashboardDataType::PERCENTAGE:
                    $precision = Arr::get($options, 'percentage_precision', 1);
                    $result['formatted_value'] = number_format($rawValue, $precision) . '%';
                    break;

                case DashboardDataType::DELTA_LAST_YEAR:
                    $lyKey = $key . '_ly';
                    $lyValue = ($interval->value != 'all' ? ($data[$lyKey] ?? 0) : $rawValue);

                    $result['formatted_value'] = Number::delta($rawValue, $lyValue);
                    $result['raw_value'] = Number::rawDelta($rawValue, $lyValue);

                    if ($interval->value != 'all') {
                        if (Arr::get($options, 'currency')) {
                            $result['tooltip'] = Number::currency($lyValue, Arr::get($options, 'currency'));
                        } else {
                            $result['tooltip'] = Number::format($lyValue);
                        }
                    }

                    $result['delta_icon'] = Number::deltaIcon(
                        $rawValue,
                        $lyValue,
                        Arr::get($options, 'inverse_delta', false)
                    );
                    break;

                default: // DashboardDataType::NUMBER
                    $result['formatted_value'] = Number::format($rawValue);
                    break;
            }

            $routeTargetData = Arr::get($routeTarget, 'route_target');
            if ($routeTargetData) {
                $result['route_target'] = $routeTargetData;
            }

            return [$interval->value => $result];
        })->toArray();
    }

    /**
     * Get dashboard table column from array data
     *
     * @param array $data The source array
     * @param string $columnFingerprint The column identifier with modifiers
     * @param array $routeTarget Optional route configuration
     * @return array
     */
    public function getDashboardTableColumnFromArray(array $data, string $columnFingerprint, array $routeTarget = []): array
    {
        $originalColumnFingerprint = $columnFingerprint;
        $dataType = DashboardDataType::NUMBER;
        $options = [];

        if (str_ends_with($columnFingerprint, '_minified')) {
            $columnFingerprint = substr($columnFingerprint, 0, -strlen('_minified'));
            $dataType = DashboardDataType::NUMBER_MINIFIED;
        }

        if (str_ends_with($columnFingerprint, '_delta')) {
            $columnFingerprint = substr($columnFingerprint, 0, -strlen('_delta'));
            $dataType = DashboardDataType::DELTA_LAST_YEAR;
        }

        if (str_ends_with($columnFingerprint, '_currency')) {
            if ($dataType != DashboardDataType::DELTA_LAST_YEAR) {
                $dataType = $dataType == DashboardDataType::NUMBER_MINIFIED
                    ? DashboardDataType::CURRENCY_MINIFIED
                    : DashboardDataType::CURRENCY;
            }
        }

        if (str_ends_with($columnFingerprint, '_org_currency')) {
            $options['currency'] = $data['organisation_currency_code'] ?? 'GBP';
        } elseif (str_ends_with($columnFingerprint, '_grp_currency')) {
            $options['currency'] = $data['group_currency_code'] ?? 'GBP';
        }

        if (str_ends_with($columnFingerprint, '_inverse')) {
            $options['inverse_delta'] = true;
            $columnFingerprint = substr($columnFingerprint, 0, -strlen('_inverse'));
        }

        if (str_ends_with($columnFingerprint, '_percentage')) {
            $dataType = DashboardDataType::PERCENTAGE;
        }

        if (in_array($columnFingerprint, ['sales', 'revenue', 'baskets_created', 'baskets_updated'])) {
            if ($dataType == DashboardDataType::NUMBER) {
                $dataType = DashboardDataType::CURRENCY;
            } elseif ($dataType == DashboardDataType::NUMBER_MINIFIED) {
                $dataType = DashboardDataType::CURRENCY_MINIFIED;
            }

            $options['currency'] = $data['shop_currency_code'] ?? 'GBP';
        }

        return [
            $originalColumnFingerprint => $this->getIntervalValuesFromArray(
                $data,
                $columnFingerprint,
                $dataType,
                $options,
                $routeTarget
            )
        ];
    }

    /**
     * Get multiple dashboard columns at once
     *
     * @param array $data The source array
     * @param array $columns Array of column fingerprints or [fingerprint => routeTarget] pairs
     * @return array
     */
    public function getDashboardColumnsFromArray(array $data, array $columns): array
    {
        $result = [];

        foreach ($columns as $key => $value) {
            if (is_numeric($key)) {
                // Simple column name without route target
                $result = array_merge(
                    $result,
                    $this->getDashboardTableColumnFromArray($data, $value)
                );
            } else {
                // Column with route target
                $result = array_merge(
                    $result,
                    $this->getDashboardTableColumnFromArray($data, $key, $value)
                );
            }
        }

        return $result;
    }

    /**
     * Sum interval values for multiple models/arrays
     *
     * @param iterable $models Collection of arrays or objects
     * @param array $fields Fields to sum
     * @return array Summed values for all intervals
     */
    public function sumIntervalValuesFromArrays(iterable $models, array $fields): array
    {
        $intervals = DateIntervalEnum::cases();
        $sums = [];

        foreach ($models as $model) {
            $modelArray = is_array($model) ? $model : (array)$model;

            foreach ($fields as $field) {
                foreach ($intervals as $interval) {
                    // Current period
                    $key = $field . '_' . $interval->value;
                    if (isset($modelArray[$key])) {
                        $sums[$key] = ($sums[$key] ?? 0) + $modelArray[$key];
                    }

                    // Last year period
                    $lyKey = $key . '_ly';
                    if (isset($modelArray[$lyKey])) {
                        $sums[$lyKey] = ($sums[$lyKey] ?? 0) + $modelArray[$lyKey];
                    }
                }
            }
        }

        return $sums;
    }
}

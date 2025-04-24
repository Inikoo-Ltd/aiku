<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Traits;

use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;

trait WithDashboard
{
    protected ?string $tabDashboardInterval                = null;

    public function getIntervalOptions(): array
    {
        return collect(DateIntervalEnum::cases())->map(function ($interval) {
            return [
                'label'      => __(strtolower(str_replace('_', ' ', $interval->name))),
                'labelShort' => __($interval->value),
                'value'      => $interval->value
            ];
        })->toArray();
    }


    public function getWidget(string $type = 'basic', int $colSpan = 1, int $rowSpan = 1, array $route = [], array $data = [], array $visual = []): array
    {
        return [
            'type' => $type,
            'col_span'  => $colSpan,
            'row_span'  => $rowSpan,
            'route' => $route,
            'visual' => $visual,
            'data' => $data
        ];
    }

    public function withTabDashboardInterval(array $tabs): static
    {
        $tab =  $this->get('tab_dashboard_interval', Arr::first($tabs));

        if (!in_array($tab, $tabs)) {
            abort(404);
        }

        $this->tabDashboardInterval = $tab;

        return $this;
    }

    public function calculatePercentageIncrease($thisYear, $lastYear): ?float
    {
        if ($lastYear == 0) {
            return $thisYear > 0 ? 100 : 0;
        }

        return (($thisYear - $lastYear) / $lastYear) * 100;
    }

    protected function getIntervalPercentage($intervalData, string $prefix, $key, $tooltip = '', $currencyCode = 'USD'): array
    {


        if ($key == 'all') {
            return [
                'amount' => $intervalData->{$prefix . '_all'} ?? null,
            ];
        }

        if (!str_starts_with($prefix, 'sales')) {
            $tooltips = "$tooltip" . $intervalData->{$prefix . '_' . $key . '_ly'} ?? 0;
        } else {
            $tooltips = "$tooltip" . Number::currency($intervalData->{$prefix . '_' . $key . '_ly'} ?? 0, $currencyCode);
        }


        return [
            'amount'     => $intervalData->{$prefix . '_' . $key} ?? null,
            'percentage' => isset($intervalData->{$prefix . '_' . $key}, $intervalData->{$prefix . '_' . $key . '_ly'})
                ? $this->calculatePercentageIncrease(
                    $intervalData->{$prefix . '_' . $key},
                    $intervalData->{$prefix . '_' . $key . '_ly'}
                )
                : null,
            'difference' => isset($intervalData->{$prefix . '_' . $key}, $intervalData->{$prefix . '_' . $key . '_ly'})
                ? $intervalData->{$prefix . '_' . $key} - $intervalData->{$prefix . '_' . $key . '_ly'}
                : null,
            'tooltip'  =>  $tooltips,
        ];
    }

    public function getAllIntervalPercentage($intervalData, string $prefix): array
    {
        $result = [];

        foreach (DateIntervalEnum::cases() as $interval) {
            $key = $interval->value;
            if ($key == 'all') {
                $result[] = [
                    'name' => __(strtolower(str_replace('_', ' ', $interval->name))),
                    'amount' => $intervalData->{$prefix . '_all'} ?? null,
                ];
                continue;
            }

            $result[] = [
                'name' => __(strtolower(str_replace('_', ' ', $interval->name))),
                'amount'     => $intervalData->{$prefix . '_' . $key} ?? null,
                'amount_ly' => $intervalData->{$prefix . '_' . $key . '_ly'} ?? null,
                'percentage' => isset($intervalData->{$prefix . '_' . $key}, $intervalData->{$prefix . '_' . $key . '_ly'})
                    ? $this->calculatePercentageIncrease(
                        $intervalData->{$prefix . '_' . $key},
                        $intervalData->{$prefix . '_' . $key . '_ly'}
                    )
                    : null,
            ];
        }

        return $result;
    }




    public function setSortedVisualData(&$visualData, $key): void
    {
        $combined = $this->sortVisualDataset(2, $visualData[$key . '_data']['labels'], $visualData[$key . '_data']['hover_labels'], $visualData[$key . '_data']['datasets'][0]['data']);
        $visualData[ $key . '_data']['labels']              = array_column($combined, 0);
        $visualData[ $key . '_data']['hover_labels']        = array_column($combined, 1);
        $visualData[ $key . '_data']['datasets'][0]['data'] = array_column($combined, 2);
    }

    public function setVisualInvoiceSales(Group|Organisation $parent, array $visualData, array $dashboard, $visualType = 'doughnut'): array
    {
        $total = $dashboard['total'];
        if (array_filter(Arr::get($visualData, 'sales_data.datasets.0.data'), fn ($value) => $value !== '0.00')) {
            $this->setSortedVisualData($visualData, 'sales');

            $dashboard['widgets']['components'][] = $this->getWidget(
                type: 'chart_display',
                data: [
                    'status'        => $total['total_sales'] < 0 ? 'danger' : '',
                    'value'         => $total['total_sales'],
                    'currency_code' => $parent->currency->code,
                    'type'          => 'currency',
                    'description'   => __('Total sales')
                ],
                visual: [
                    'type'  => $visualType,
                    'value' => [
                        'labels'         => $visualData['sales_data']['labels'],
                        'hover_labels'   => $visualData['sales_data']['hover_labels'],
                        'datasets'       => [
                            'data' => Arr::flatten($visualData['sales_data']['datasets']),
                        ],
                    ],
                ]
            );
        }
        return $dashboard;
    }

    public function setVisualInvoices(array $visualData, array $dashboard, string $visualType = 'doughnut'): array
    {
        $total = $dashboard['total'];
        if (array_filter(Arr::get($visualData, 'invoices_data.datasets.0.data'))) {
            $this->setSortedVisualData($visualData, 'invoices');
            $dashboard['widgets']['components'][] = $this->getWidget(
                type: 'chart_display',
                data: [
                    'status'        => $total['total_invoices'] < 0 ? 'danger' : '',
                    'value'       => $total['total_invoices'],
                    'type'        => 'number',
                    'description' => __('Total invoices'),
                ],
                visual: [
                    'type'  => $visualType,
                    'value' => [
                        'labels'         => Arr::get($visualData, 'invoices_data.labels'),
                        'hover_labels'   => Arr::get($visualData, 'invoices_data.hover_labels'),
                        'datasets'       => [
                            'data' => Arr::flatten($visualData['invoices_data']['datasets']),
                        ],
                    ],
                ]
            );
        }

        return $dashboard;
    }

    public function setVisualAvgInvoices(Group|Organisation $parent, array $visualData, array $dashboard, $visualType = 'bar'): array
    {
        $invoiceValues = Arr::get($visualData, 'sales_data.datasets.0.data');
        $invoiceTotal = Arr::get($visualData, 'invoices_data.datasets.0.data');
        if (array_filter($invoiceTotal)) {
            $hoverLabels = Arr::get($visualData, 'invoices_data.hover_labels');
            $labels = Arr::get($visualData, 'invoices_data.labels');

            $averageDataset = [];

            foreach ($invoiceTotal as $key => $value) {
                if ($value > 0) {
                    $averageDataset[] = $invoiceValues[$key] / $value;
                } else {
                    $averageDataset[] = 0;
                }

            }

            $dashboard['widgets']['components'][] = $this->getWidget(
                type: 'chart_display',
                data: [
                    'currency_code' => $parent->currency->code,
                    'description' => __('Average invoice value')
                ],
                visual: [
                    'type'  => $visualType,
                    'value' => [
                        'labels'         => $labels,
                        'hover_labels'  => $hoverLabels,
                        'datasets'       => [
                            [
                                'data' => $averageDataset,
                                // 'backgroundColor' => $this->getReadableColor($labels),
                            ]
                        ]
                    ],
                ]
            );
        }

        return $dashboard;
    }


    public function sortVisualDataset($keyBaseSorted, ...$data): array
    {
        $combined = array_map(null, ...$data);

        usort($combined, function ($a, $b) use ($keyBaseSorted) {
            return floatval($b[$keyBaseSorted]) <=> floatval($a[$keyBaseSorted]);
        });

        return $combined;
    }

    public function getMoreColor(array $colorMaps, int $needed, array $labels): array
    {
        $added = 0;
        $i = 0;

        while ($added < $needed && isset($labels[$i])) {
            $hash = md5((string) $labels[$i]);
            $r = hexdec(substr($hash, 0, 2));
            $g = hexdec(substr($hash, 2, 2));
            $b = hexdec(substr($hash, 4, 2));
            $hexColor = sprintf("#%02X%02X%02X", $r, $g, $b);

            while (isset($colorMaps[$hexColor])) {
                $hash = md5($hash);
                $r = hexdec(substr($hash, 0, 2));
                $g = hexdec(substr($hash, 2, 2));
                $b = hexdec(substr($hash, 4, 2));
                $hexColor = sprintf("#%02X%02X%02X", $r, $g, $b);
            }

            $colorMaps[$hexColor] = $hexColor;
            $added++;
            $i++;
        }

        return $colorMaps;
    }

    public function getReadableColor(array $labels): array
    {
        $colorMaps = [];

        $total = count($labels);

        for ($i = 0; $i < min($total, 121); $i++) {
            $hash = md5($labels[$i]);
            $hue = 179 + (hexdec(substr($hash, 0, 6)) % (300 - 179));
            $saturation = 90;
            $lightness = 65 / 0.9;

            $hexColor = $this->hslToHex($hue, $saturation, $lightness);
            $colorMaps[$hexColor] = $hexColor;
        }



        if (count($colorMaps) < $total) {
            $neededLabels = array_values(array_diff($labels, array_keys($colorMaps)));
            $colorMaps = $this->getMoreColor($colorMaps, $total - count($colorMaps), $neededLabels);
        }

        return array_values($colorMaps);
    }

    private function hslToHex($h, $s, $l): string
    {
        $s /= 100;
        $l /= 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        list($r, $g, $b) = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x]
        };

        return sprintf("#%02X%02X%02X", ($r + $m) * 255, ($g + $m) * 255, ($b + $m) * 255);
    }


}

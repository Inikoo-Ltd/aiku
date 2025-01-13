<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Dec 2024 00:41:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dashboards;

use App\Actions\OrgAction;
use App\Actions\Traits\WithDashboard;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ShowGroupDashboard extends OrgAction
{
    use WithDashboard;
    public function handle(Group $group): Response
    {

        $userSettings = auth()->user()->settings;
        $selectedInterval = Arr::get($userSettings, 'selected_interval', 'all');

        $data = $this->getDataDashboard($group, $selectedInterval);

        return Inertia::render(
            'Dashboard/GrpDashboard',
            [
                'breadcrumbs'       => $this->getBreadcrumbs(__('Dashboard')),
                'groupStats'        => $data,
                'interval_options'  => $this->getIntervalOptions(),
                'dashboard_stats' => $this->getDashboard($group, $userSettings),
                // 'dashboard_stats' => [
                //     'interval_options'  => $this->getIntervalOptions(),
                //     'settings' => $userSettings,
                //     'widgets' => [
                //         'column_count'    => $data['organisations']->count(),
                //         'components'    => [
                //             // [
                //             //     'type' => 'basic',
                //             //     'col_span'  => 1,
                //             //     'row_span'  => 2,
                //             //     'data' => [
                //             //         'value'         => 0,
                //             //         'description'   => 'xxxxxxx',
                //             //         'status'    => 'success',
                //             //     ]
                //             // ],
                //             // [
                //             //     'type' => 'basic',
                //             //     'col_span'  => 1,
                //             //     'row_span'  => 1,
                //             //     'data' => [
                //             //         'value'         => 180000,
                //             //         'description'   => 'ggggggg',
                //             //         'status'    => 'danger',
                //             //         'type'      => 'currency',
                //             //         'currency_code' => 'GBP'
                //             //     ]
                //             // ],
                //             [
                //                 'type' => 'basic',
                //                 'col_span'  => 1,
                //                 'row_span'  => 1,
                //                 'route' => [
                //                     'name' => 'grp.org.dashboard.show',
                //                     'params' => [$group->slug]
                //                 ],
                //                 'data' => [
                //                     'value'         => 662137,
                //                     'description'   => 'ggggggg',
                //                     // 'status'    => 'information',
                //                     'type'      => 'currency',
                //                     'currency_code' => 'GBP'
                //                 ]
                //             ],
                //             // [
                //             //     'type' => 'basic',
                //             //     'col_span'  => 1,
                //             //     'row_span'  => 1,
                //             //     'data' => [
                //             //         'value'         => 99,
                //             //         'type'      => 'number',
                //             //         'description'   => 'Hell owrodl',
                //             //         'status'    => 'warning',
                //             //     ]
                //             // ],
                //             // [
                //             //     'type' => 'basic',
                //             //     'col_span'  => 3,
                //             //     'row_span'  => 1,
                //             //     'data' => [
                //             //         'value'         => 44300,
                //             //         'description'   => '6666',
                //             //         'status'    => 'information',
                //             //         // 'status'    => 'success',
                //             //     ]
                //             // ],
                //         ]
                //     ],
                // ],
            ]
        );
    }

    public function asController(): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, []);
        return $this->handle($group);
    }

    public function getDashboard(Group $group, array $userSettings): array
    {
        $selectedInterval = Arr::get($userSettings, 'selected_interval', 'all');

        // $data = $this->getDataDashboard($group, $selectedInterval);

        $organisations = $group->organisations()->where('type', '!=', 'agent')->get();
        $dashboard = [
            'interval_options'  => $this->getIntervalOptions(),
            'settings' => $userSettings,
            'table' => [],
            'widgets' => [
                'column_count'    => $organisations->count(),
                'components' => []
            ]
        ];

        $dashboard['table'] = $organisations->map(function (Organisation $organisation) use ($selectedInterval, $group, &$dashboard) {
            $responseData = [
                'name'      => $organisation->name,
                'slug'      => $organisation->slug,
                'code'      => $organisation->code,
                'type'      => $organisation->type,
                'currency'  => $organisation->currency,
            ];
            if ($organisation->salesIntervals !== null) {
                $responseData['interval_percentages']['sales'] = $this->getIntervalPercentage(
                    $organisation->salesIntervals,
                    'sales_org_currency',
                    $selectedInterval,
                );

                $amount = $responseData['interval_percentages']['sales']['amount'];
                $dashboard['widgets']['components'][] = [
                    'type' => 'basic',
                    'col_span'  => 1,
                    'row_span'  => 1,
                    'route' => [
                        'name' => 'grp.org.dashboard.show',
                        'params' => [$group->slug]
                    ],
                    'data' => [
                        'value'         =>  $amount,
                        'description'   => __('Sales For ') . $responseData['name'],
                        'status'    => $amount < 0 ? 'danger' : '',
                        'type'      => 'currency',
                        'currency_code' => $organisation->currency->code
                    ]
                ];
            }

            if ($organisation->orderingIntervals !== null) {
                $responseData['interval_percentages']['invoices'] = $this->getIntervalPercentage(
                    $organisation->orderingIntervals,
                    'invoices',
                    $selectedInterval,
                );
            }

            if ($organisation->orderingIntervals !== null) {
                $responseData['interval_percentages']['refunds'] = $this->getIntervalPercentage(
                    $organisation->orderingIntervals,
                    'refunds',
                    $selectedInterval,
                );
            }


            return $responseData;
        });

        return $dashboard;

    }

    public function getDataDashboard(Group $group, $selectedInterval): array
    {
        return [
            'currency' => $group->currency,
            'total'    => [
            $selectedInterval => [
                'total_sales'    => $group->organisations->sum(fn ($organisations) => $organisations->salesIntervals?->{"sales_org_currency_" . $selectedInterval} ?? 0),
                'total_invoices' => $group->organisations->sum(fn ($organisations) => $organisations->orderingIntervals?->{"invoices_{$selectedInterval}"} ?? 0),
                'total_refunds'  => $group->organisations->sum(fn ($organisations) => $organisations->orderingIntervals?->{"refunds_{$selectedInterval}"} ?? 0),
            ],
            ],
            'organisations' => $group->organisations()->where('type', '!=', 'agent')->get()->map(function (Organisation $organisation) use ($selectedInterval) {
                $responseData = [
                    'name'      => $organisation->name,
                    'slug'      => $organisation->slug,
                    'code'      => $organisation->code,
                    'type'      => $organisation->type,
                    'currency'  => $organisation->currency,
                ];
                if ($organisation->salesIntervals !== null) {
                    $responseData['interval_percentages']['sales'] = $this->getIntervalPercentage(
                        $organisation->salesIntervals,
                        'sales_org_currency',
                        $selectedInterval,
                    );
                }

                if ($organisation->orderingIntervals !== null) {
                    $responseData['interval_percentages']['invoices'] = $this->getIntervalPercentage(
                        $organisation->orderingIntervals,
                        'invoices',
                        $selectedInterval,
                    );
                }

                if ($organisation->orderingIntervals !== null) {
                    $responseData['interval_percentages']['refunds'] = $this->getIntervalPercentage(
                        $organisation->orderingIntervals,
                        'refunds',
                        $selectedInterval,
                    );
                }

                return $responseData;
            }),
        ];
    }

    public function calculatePercentageIncrease($thisYear, $lastYear): ?float
    {
        if ($lastYear == 0) {
            return $thisYear > 0 ? null : 0;
        }

        return (($thisYear - $lastYear) / $lastYear) * 100;
    }

    protected function getIntervalPercentage($intervalData, string $prefix, $key): array
    {
        $result = [];

        if ($key == 'all') {
            $result = [
                'amount' => $intervalData->{$prefix . '_all'} ?? null,
            ];
            return $result;
        }

        $result = [
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
        ];

        return $result;
    }

    public function getBreadcrumbs($label = null): array
    {
        return [
            [

                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-tachometer-alt-fast',
                    'label' => $label,
                    'route' => [
                        'name' => 'grp.dashboard.show'
                    ]
                ]

            ],

        ];
    }
}

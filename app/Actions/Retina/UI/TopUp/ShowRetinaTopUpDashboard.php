<?php

/*
 * author Arya Permana - Kirin
 * created on 06-05-2025-14h-58m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\UI\TopUp;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\CRM\Customer;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaTopUpDashboard extends RetinaAction
{
    public function asController(ActionRequest $request): Customer
    {
        $this->initialisation($request);
        return $this->customer;
    }

    public function htmlResponse(Customer $customer): Response
    {
        $unpaidOrders = $customer->orders()->whereNotIn('state', [OrderStateEnum::CANCELLED, OrderStateEnum::CREATING])
                    ->whereRaw('payment_amount < total_amount')->get();

        $count = $unpaidOrders->count();

        $total = $unpaidOrders->sum(function ($order) {
            return $order->total_amount - $order->payment_amount;
        });

        $unpaidOrdersData = null;

        if($count > 0)
        {
            $unpaidOrdersData = [
                'count'  => $count,
                'total'  => $total,
            ];
        }

        return Inertia::render('Storage/RetinaTopUpDashboard', [
            'title'        => __('Top Ups Dashboard'),
            'breadcrumbs'    => $this->getBreadcrumbs(),
            'pageHead'    => [

                'title'         => __('Top Up Dashboard'),
                'icon'          => [
                    'icon'  => ['fal', 'fa-tachometer-alt'],
                    'title' => __('TopUp Dashboard')
                ],

            ],
            'unpaid_orders' => [
                'count'  => $count,
                'total'  => $total,
            ],
            'amount_shortcuts' => $unpaidOrdersData,
            'topUpData'  => $this->getTopUpData($customer),
            'balance' => $customer->balance,
            'currency' => CurrencyResource::make($customer->shop->currency)->getArray()
        ]);
    }

    public function getTopUpData(Customer $customer): array
    {
        $stats = [];

        $stats['topUps'] = [
            'label'         => __('Top Ups'),
            'count'         => $customer->stats->number_top_ups,
            'description'   => __('previous top ups'),
            'route'         => [
                'name' => 'retina.top_up.index'
            ]
        ];

        return $stats;
    }

    public function getBreadcrumbs(): array
    {

        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.top_up.dashboard'
                            ],
                            'label' => __('Top Up dashboard'),
                        ]
                    ]
                ]
            );

    }
}

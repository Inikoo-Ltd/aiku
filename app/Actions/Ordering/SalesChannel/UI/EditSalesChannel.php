<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Ordering\SalesChannel\UI;

use App\Actions\OrgAction;
use App\Models\Ordering\SalesChannel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditSalesChannel extends OrgAction
{
    public function htmlResponse(SalesChannel $salesChannel): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($salesChannel),
                'title'       => __("$salesChannel->name"),
                'pageHead'    => [
                    'title'   =>  __("$salesChannel->name"),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Exit Edit'),
                            'route' => [
                                'name'       => 'grp.sales_channels.show',
                                'parameters' => [
                                    'salesChannel' => $salesChannel->slug
                                ]
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'   => __('Show in Dashboard'),
                            'current' => true,
                            'fields'  => [
                                'show_in_dashboard' => [
                                    'type'  => 'toggle',
                                    'label' => __('Show in Dashboard'),
                                    'value' => $salesChannel->show_in_dashboard,
                                ],
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.sales_channels.update',
                            'parameters' => [
                                'salesChannel' => $salesChannel->id
                            ]
                        ]
                    ]
                ]
            ]
        );
    }

    public function asController(SalesChannel $salesChannel, ActionRequest $request): SalesChannel
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $salesChannel;
    }

    public function getBreadcrumbs(SalesChannel $salesChannel): array
    {
        return array_merge(
            ShowSalesChannel::make()->getBreadcrumbs($salesChannel),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Edit'),
                        'route' => [
                            'name'       => 'grp.sales_channels.edit',
                            'parameters' => [
                                'salesChannel' => $salesChannel->slug
                            ]
                        ]
                    ]
                ]
            ]
        );
    }
}

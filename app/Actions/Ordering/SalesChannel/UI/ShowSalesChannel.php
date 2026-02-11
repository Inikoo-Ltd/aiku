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

class ShowSalesChannel extends OrgAction
{
    public function htmlResponse(SalesChannel $salesChannel): Response
    {
        return Inertia::render(
            'Devel/Dummy',
            [
                'breadcrumbs' => $this->getBreadcrumbs($salesChannel),
                'title'       => __("$salesChannel->name"),
                'pageHead'    => [
                    'title'   =>  __("$salesChannel->name"),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'icon'  => 'fal fa-pencil',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => 'grp.sales_channels.edit',
                                'parameters' => [
                                    'salesChannel' => $salesChannel->slug
                                ]
                            ]
                        ]
                    ]
                ],
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
            IndexSalesChannels::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __("$salesChannel->name"),
                        'route' => [
                            'name'       => 'grp.sales_channels.show',
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

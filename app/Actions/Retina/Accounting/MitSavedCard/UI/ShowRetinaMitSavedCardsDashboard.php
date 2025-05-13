<?php

/*
 * author Arya Permana - Kirin
 * created on 09-05-2025-10h-20m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Accounting\MitSavedCard\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Accounting\MitSavedCard\MitSavedCardStateEnum;
use App\Http\Resources\Accounting\MitSavedCardResource;
use App\Models\CRM\Customer;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaMitSavedCardsDashboard extends RetinaAction
{
    public function asController(ActionRequest $request): Customer
    {
        $this->initialisation($request);

        return $this->customer;
    }

    public function htmlResponse(): Response
    {
        $title = __('Credit Card Dashboard');


        return Inertia::render('Dropshipping/DashboardRetinaMitCreditCards', [
            'title'       => $title,
            'breadcrumbs' => $this->getBreadcrumbs(),
            'pageHead'    => [

                'title'   => $title,
                'icon'    => [
                    'icon'  => ['fal', 'fa-tachometer-alt'],
                    'title' => $title
                ],
                'actions' => [
                    [
                        'type'  => 'button',
                        'style' => 'create',
                        'label' => __('Save Credit Card'),
                        'route' => [
                            'name' => 'retina.dropshipping.mit_saved_cards.create',
                        ],
                    ]
                ]

            ],

            'mitSavedCards' => MitSavedCardResource::collection(
                $this->customer->mitSavedCard()->where('state', MitSavedCardStateEnum::SUCCESS)->orderBy('priority')->get()
            ),

            'delete_route' => [
                'name'   => 'retina.models.mit_saved_card.delete',
                'method' => 'delete',
            ]
        ]);
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
                                'name' => 'retina.dropshipping.mit_saved_cards.dashboard'
                            ],
                            'label' => __('Credit Card Dashboard'),
                        ]
                    ]
                ]
            );
    }
}

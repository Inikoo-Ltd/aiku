<?php

/*
 * author Arya Permana - Kirin
 * created on 10-01-2025-10h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Pallet\UI;

use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Fulfilment\Pallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditRetinaPallet extends RetinaAction
{
    public function handle(Pallet $pallet): Pallet
    {
        return $pallet;
    }

    public function jsonResponse(LengthAwarePaginator $pallet): AnonymousResourceCollection
    {
        return PalletResource::collection($pallet);
    }


    public function htmlResponse(Pallet $pallet, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $pallet,
                    $request->route()->getName()
                ),
                'title'       => __('Edit pallet'),
                'pageHead'    => [
                    'icon'      => 'fal fa-pallet',
                    'model'     => __('Edit Pallet'),
                    'title'      => $pallet->reference,
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'label'  => __('Properties'),
                            'icon'   => ['fal', 'fa-narwhal'],
                            'fields' => [
                                'customer_reference' => [
                                    'type'    => 'input',
                                    'label'   => __('customer reference'),
                                    'value'   => $pallet->customer_reference,
                                    'required' => false
                                ],
                                'notes' => [
                                    'type'    => 'input',
                                    'label'   => __('notes'),
                                    'value'   => $pallet->notes,
                                    'required' => false
                                ],
                            ]
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'retina.models.pallet.update',
                            'parameters' => [$pallet->id]
                        ],
                    ]
                ],
            ]
        );
    }

    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->initialisation($request);

        return $this->handle($pallet);
    }

    public function getBreadcrumbs(Pallet $pallet, string $routeName): array
    {
        return ShowRetinaPallet::make()->getBreadcrumbs(
            pallet: $pallet,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            suffix: '('.__('Editing').')'
        );
    }
}

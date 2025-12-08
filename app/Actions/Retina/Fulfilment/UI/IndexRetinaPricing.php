<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 Jan 2025 01:27:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\UI;

use App\Actions\Retina\Pricing\UI\WithRetinaPricingSubNavigation;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaPricing extends RetinaAction
{
    use WithRetinaPricingSubNavigation;

    public function asController(ActionRequest $request): FulfilmentCustomer
    {
        abort(401);
        $this->initialisation($request);

        return $this->customer->fulfilmentCustomer;
    }

    public function htmlResponse(FulfilmentCustomer $fulfilmentCustomer): Response
    {
        $shop = $this->shop;

        $assets = [];
        foreach ($shop->assets as $asset) {
            if ($asset->type->value == 'charge') {
                continue;
            }
            $price = $asset->price;
            $assets[] = [
                'name' => $asset->name,
                'type' => $asset->type,
                'price' => $price,
            ];
        }

        return Inertia::render(
            'Storage/RetinaStoragePricing',
            [
                'title' => __('Pricing'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    request()->route()->getName(),
                ),
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'fa-usd-circle'],
                        'tooltip' => __('Prices'),
                    ],
                    'title' => 'Prices',
                    'subNavigation' => $this->getPricingNavigation($fulfilmentCustomer->fulfilment),
                ],
                'currency' => CurrencyResource::make($fulfilmentCustomer->fulfilment->shop->currency),

                'assets' => $assets,
            ]
        );
    }

    public function getBreadcrumbs(string $routeName): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.fulfilment.pricing.index',
                            ],
                            'label' => __('Prices'),
                        ],
                    ],
                ]
            );

    }
}

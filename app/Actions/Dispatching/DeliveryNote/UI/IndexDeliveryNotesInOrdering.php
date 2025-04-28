<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Mar 2025 22:30:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Ordering\UI\WithOrderingAuthorisation;
use App\Actions\OrgAction;
use App\Enums\UI\DeliveryNotes\DeliveryNotesTabsEnum;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexDeliveryNotesInOrdering extends OrgAction
{
    use WithOrderingAuthorisation;
    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        return IndexDeliveryNotes::run($shop, $prefix);
    }

    public function tableStructure(Shop $shop, $prefix = null): Closure
    {
        return IndexDeliveryNotes::make()->tableStructure($shop, $prefix);
    }

    public function jsonResponse(LengthAwarePaginator $deliveryNotes): AnonymousResourceCollection
    {
        return DeliveryNotesResource::collection($deliveryNotes);
    }

    public function htmlResponse(LengthAwarePaginator $deliveryNotes, ActionRequest $request): Response
    {

        $shop = $request->route()->parameters()['shop'];


        $subNavigation = null;

        $title      = __('Delivery notes');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-truck'],
            'title' => $title
        ];
        $afterTitle = null;
        $iconRight  = null;
        $actions    = null;


        return Inertia::render(
            'Org/Dispatching/DeliveryNotes',
            [
                'breadcrumbs'                                => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'                                      => $title,
                'pageHead'                                   => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'                                       => DeliveryNotesResource::collection($deliveryNotes),
            ]
        )->table($this->tableStructure(shop: $shop));
    }



    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request)->withTab(DeliveryNotesTabsEnum::values());
        return $this->handle($shop);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Delivery notes'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            $headCrumb(
                [
                    'name' => $routeName,
                    'parameters' => $routeParameters
                ]
            )
        );
    }
}

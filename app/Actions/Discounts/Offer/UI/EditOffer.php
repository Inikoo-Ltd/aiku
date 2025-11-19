<?php

/*
 * author Louis Perez
 * created on 19-11-2025-13h-31m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOffer extends OrgAction
{
    /**
     * @throws Exception
     */
    public function handle(Offer $offer, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $offer,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Edit Offer'),
                'pageHead'    => [
                    'title' => __('Edit Offer')
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('name'),
                                'fields' => [
                                    'name'        => [
                                        'type'        => 'input',
                                        'label'       => __('name'),
                                        'placeholder' => __('Name'),
                                        'required'    => true,
                                        'value'       => $offer->name,
                                    ],
                                ]
                            ]
                        ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.org.shops.show.discounts.offers.update',
                            'parameters' => $request->route()->originalParameters(),
                        ],
                    ]
                ],

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, Shop $shop, Offer $offer, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer, $request);
    }

    public function getBreadcrumbs(Offer $offer, string $routeName, array $routeParameters): array
    {
        return ShowOffer::make()->getBreadCrumbs(
            offer: $offer,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}

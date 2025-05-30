<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:35:41 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Http\Resources\Web\WebpageResource;
use App\Http\Resources\Web\WebpageWorkshopResource;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWebpageWorkshop extends OrgAction
{
    use WithWebEditAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisationFromShop($shop, $request);

        return $webpage;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $webpage;
    }

    public function htmlResponse(Webpage $webpage, ActionRequest $request): Response
    {

        return Inertia::render(
            'Org/Web/WebpageWorkshop',
            [
                'title'       => $webpage->code . ' ' . __("workshop"),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'     => $webpage->code,
                    'afterTitle'   => [
                        'label'     => '../' . $webpage->url,
                    ],
                    'icon'      => [
                        'title' => __('webpage'),
                        'icon'  => 'fal fa-browser'
                    ],
                    'model' => __('Workshop'),

                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit workshop'),
                            'route' => [
                                'name'       => preg_replace('/workshop$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ],
                        [
                            'type'  => 'button',
                            'style' => 'save',
                            'label' => __('publish'),
                            'route' => [
                                'name'       => 'grp.models.webpage.publish',
                                'parameters' => $webpage->id,
                                'method'     => 'post'
                            ]
                        ],
                    ],
                ],
                'webpage'     => WebpageWorkshopResource::make($webpage)->getArray(),


                'webBlockTypes' => WebBlockTypesResource::collection(
                    $this->organisation->group->webBlockTypes()->where('fixed', false)->where('scope', 'webpage')->get()
                )

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowWebpage::make()->getBreadcrumbs(
            $routeName,
            $routeParameters,
            suffix: '('.__('workshop').')'
        );
    }


}

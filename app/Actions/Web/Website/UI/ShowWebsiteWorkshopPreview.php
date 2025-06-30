<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:35:41 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Enums\UI\Web\WebsiteWorkshopTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWebsiteWorkshopPreview extends OrgAction
{
    use WithWebAuthorisation;


    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Website
    {
        $this->initialisation($organisation, $request)->withTab(WebsiteWorkshopTabsEnum::values());

        return $website;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $website;
    }


    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        return Inertia::render(
            'Web/PreviewWorkshop',
            [
                'title'       => __("Website's preview"),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $website,
                    str_replace('preview', 'show', $request->route()->getName()),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [

                    'title' => __('Preview'),

                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit Preview'),
                            'route' => [
                                'name'       => str_replace('preview', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ],

                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('workshop'),
                            'icon'  => ["fal", "fa-drafting-compass"],
                            'route' => [
                                'name'       => str_replace('preview', 'workshop', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : [],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(Website $website, string $routeName, array $routeParameters): array
    {
        return ShowWebsite::make()->getBreadcrumbs(
            $website,
            $routeName,
            $routeParameters,
            suffix: '('.__('preview').')'
        );
    }


}

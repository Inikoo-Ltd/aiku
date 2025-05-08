<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Web\Website;

class ShowBlueprint extends OrgAction
{
    use AsAction;
    use WithFooterSubNavigation;


    public function handle(Webpage $webpage): Webpage
    {
        return $webpage;
    }

    public function htmlResponse(Webpage $webpage, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Web/Workshop/Blueprint/ProductCategoryBlueprint',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $webpage,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('blueprint'),
                'pageHead'    => [
                    'title'    => $webpage->code,
                    'icon'     => [
                        'title' => __('blueprint'),
                        'icon'  => 'fal fa-file-code'
                    ],
                ],
                'showcase' => GetBlueprintShowcase::run($webpage),
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->scope instanceof Fulfilment) {
            $this->canEdit = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");

            return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.view");
        }

        $this->canEdit = $request->user()->authTo("shops.{$this->shop->id}.edit");

        return $request->user()->authTo("shops.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->asAction = true;
        $this->parent   = $webpage;
        $this->scope    = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($webpage);
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->parent = $webpage;
        $this->scope  = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($webpage);
    }


    public function getBreadcrumbs(Webpage $webpage, string $routeName, array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (Webpage $webpage, array $routeParameters, string $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Workshop')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => 'Footer',
                        ],

                    ],
                    'suffix'         => $suffix
                ],
            ];
        };



        return match ($routeName) {
            'grp.org.shops.show.web.webpages.workshop.footer' => array_merge(
                ShowWebpageWorkshop::make()->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                $headCrumb(
                    $webpage,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.web.webpages.workshop',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.web.webpages.workshop.footer',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

}

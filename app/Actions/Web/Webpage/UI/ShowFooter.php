<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 25 Apr 2024 16:56:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Web\Website\GetWebsiteWorkshopFooter;
use App\Actions\Web\Website\UI\ShowWebsiteWorkshop;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Http\Resources\Web\WebBlockTypesResource;

class ShowFooter extends OrgAction
{
    use AsAction;
    use WithFooterSubNavigation;


    private Website $website;

    private Webpage|Website $parent;

    private Fulfilment|Shop $scope;

    public function handle(Website $website): Website
    {
        return $website;
    }

    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Web/Workshop/Footer/FooterWorkshop',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $website,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('footer'),
                'pageHead'    => [
                    'subNavigation' => $this->getFooterSubNavigation($website),
                    'title'    => $website->code,
                    'icon'     => [
                        'title' => __('footer'),
                        'icon'  => 'fal fa-browser'
                    ],
                    'actions'            => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit workshop'),
                            'route' => ($website->shop->type === ShopTypeEnum::FULFILMENT) ? [
                                'name'       => 'grp.org.fulfilments.show.web.websites.workshop',
                                'parameters' => [
                                    'organisation' => $website->organisation,
                                    'fulfilment' => $website->shop->slug,
                                    'website' => $website
                                ],
                            ] : [
                                'name'       => 'grp.org.shops.show.web.websites.workshop',
                                'parameters' => [
                                    'organisation' => $website->organisation->slug,
                                    'shop' => $website->shop->slug,
                                    'website' => $website->slug
                                ],
                            ]
                        ],
                        [
                            'type'  => 'button',
                            'style' => 'primary',
                            'icon'  => ["fas", "fa-rocket"],
                            'label' => __('Publish'),
                            'route' => [
                                'method'     => 'post',
                                'name'       => 'grp.models.website.publish.footer',
                                'parameters' => [
                                    'website' => $website->id
                                ],
                            ]
                        ],
                    ],
                ],

                'uploadImageRoute' => [
                    'name'       => 'grp.models.website.footer.images.store',
                    'parameters' => [
                        'website' => $website->id
                    ]
                ],

                'autosaveRoute' => [
                    'name'       => 'grp.models.website.autosave.footer',
                    'parameters' => [
                        'website' => $website->id
                    ]
                ],

                'domain' => $website->domain,
                'data' => GetWebsiteWorkshopFooter::run($website),
                'webBlockTypes' => WebBlockTypesResource::collection(
                    $this->organisation->group->webBlockTypes()->where('fixed', false)->where('scope', 'website')->get()
                )
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

    public function asController(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->parent = $website;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $website;
    }

    public function inShop(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Website
    {
        $this->asAction = true; // @Raul Remove this later, i dont know the permissions (just for make it works temporarily)
        $this->parent   = $website;
        $this->scope    = $shop;
        $this->initialisationFromShop($shop, $request);

        return $website;
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->parent = $website;
        $this->scope  = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $website;
    }

    public function getBreadcrumbs(Website $website, string $routeName, array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (Website $website, array $routeParameters, string $suffix) {
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
            'grp.org.shops.show.web.websites.workshop.footer' => array_merge(
                ShowWebsiteWorkshop::make()->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                $headCrumb(
                    $website,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.web.websites.workshop',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.web.websites.workshop.footer',
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

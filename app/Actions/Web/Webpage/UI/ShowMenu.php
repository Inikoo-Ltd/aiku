<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 25 Apr 2024 16:56:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Web\WebBlockTypeCategory\UI\IndexWebBlockTypeCategories;
use App\Actions\Web\Website\GetWebsiteWorkshopMenu;
use App\Enums\Web\WebBlockTypeCategory\WebBlockTypeCategorySlugEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Http\Resources\Web\WebBlockTypeCategoryResource;

class ShowMenu extends OrgAction
{
    use AsAction;


    private Website $website;

    private Webpage|Website $parent;
    /**
     * @var array|\ArrayAccess|mixed
     */
    private Fulfilment|Shop $scope;

    public function handle(Website $website): Website
    {
        return $website;
    }

    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Web/Workshop/Menu/MenuWorkshop',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __("Website Menu's Workshop"),
                'pageHead'    => [
                    'title'    => __("Menu's Workshop"),
                    'model'    => $website->name,
                    'icon'     => [
                        'tooltip' => __('Header'),
                        'icon'    => 'fal fa-browser'
                    ],
                    'meta'      => [
                        [
                            'key'      => 'website',
                            'label'    => $website->domain,
                            'leftIcon' => [
                                'icon'  => 'fal fa-globe'
                            ]
                        ]
                    ],
                    'actions'            => [
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
                            'style' => 'primary',
                            'icon'  => ["fas", "fa-rocket"],
                            'label' => __('Publish'),
                            'route' => [
                                'method'     => 'post',
                                'name'       => 'grp.models.website.publish.menu',
                                'parameters' => [
                                    'website' => $website->id
                                ],
                            ]
                        ],
                    ],
                ],

                'uploadImageRoute' => [
                    'name'       => 'grp.models.website.menu.images.store',
                    'parameters' => [
                        'website' => $website->id
                    ]
                ],

                'autosaveRoute' => [
                    'name'       => 'grp.models.website.autosave.menu',
                    'parameters' => [
                        'website' => $website->id
                    ]
                ],

                'data' => GetWebsiteWorkshopMenu::run($website),
                'webBlockTypeCategories' => WebBlockTypeCategoryResource::collection(IndexWebBlockTypeCategories::run(WebBlockTypeCategorySlugEnum::MENU->value))
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->scope instanceof Fulfilment) {
            $this->canEdit = $request->user()->hasPermissionTo("fulfilment-shop.{$this->fulfilment->id}.edit");

            return $request->user()->hasPermissionTo("fulfilment-shop.{$this->fulfilment->id}.view");
        }

        $this->canEdit = $request->user()->hasPermissionTo("shops.{$this->shop->id}.edit");

        return $request->user()->hasPermissionTo("shops.{$this->shop->id}.view");

    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->parent = $website;
        $this->scope  = $fulfilment;
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

    public function getBreadcrumbs($routeName, $routeParameters): array
    {
        return [];
    }

}

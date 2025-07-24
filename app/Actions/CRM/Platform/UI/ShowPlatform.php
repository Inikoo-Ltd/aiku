<?php

/*
 * author Arya Permana - Kirin
 * created on 10-03-2025-11h-23m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\CRM\Platform\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\UI\CRM\PlatformTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPlatform extends OrgAction
{
    use WithCRMAuthorisation;

    private Shop $parent;

    public function handle(Platform $platform): Platform
    {
        return $platform;
    }

    public function asController(Organisation $organisation, Shop $shop, Platform $platform, ActionRequest $request): Platform
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(PlatformTabsEnum::values());
        return $this->handle($platform);
    }

    public function htmlResponse(Platform $platform, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Shop/CRM/Platform',
            [
                'title'       => __('collection'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'     => $platform->name,
                    'model'     => __('platform'),
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'fa-user-plus'],
                            'title' => __('platform')
                        ]
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => PlatformTabsEnum::navigation()
                ],
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Platform $platform, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Platforms')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $platform->slug,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $platform = Platform::where('slug', $routeParameters['platform'])->first();

        return match ($routeName) {
            'grp.org.shops.show.crm.platforms.show' =>
            array_merge(
                IndexPlatforms::make()->getBreadcrumbs('grp.org.shops.show.crm.platforms.index', $routeParameters),
                $headCrumb(
                    $platform,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.platforms.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.platforms.show',
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

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:35:41 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\UI\WithInertia;
use App\Actions\Web\HasWorkshopAction;
use App\Actions\Web\Webpage\WithWebpageSubNavigation;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Enums\UI\Web\BlogWebpageTabsEnum;
use App\Enums\UI\Web\WebpageTabsEnum;
use App\Http\Resources\Web\WebpageResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowBlogWebpage extends OrgAction
{
    use AsAction;
    use WithInertia;
    use HasWorkshopAction;
    use WithWebAuthorisation;
    use WithWebpageSubNavigation;


    public function asController(Organisation $organisation, Shop $shop, Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisationFromShop($shop, $request)->withTab(WebpageTabsEnum::values());

        return $webpage;
    }

    public function htmlResponse(Webpage $webpage, ActionRequest $request): Response
    {

        $actions = [];

        $actions = array_merge($actions, $this->workshopActions($request));

        $subNavigationRoot = '';

        return Inertia::render(
            'Org/Web/Webpage',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Webpage'),
                'pageHead'    => [
                    'title'         => $webpage->code,
                    'afterTitle'    => [
                        'label' => '../'.$webpage->url,
                    ],
                    'icon'          => [
                        'title' => __('Webpage'),
                        'icon'  => 'fal fa-browser'
                    ],
                    'iconRight'     => $webpage->state->stateIcon()[$webpage->state->value],
                    'actions'       => $actions,
                ],

                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => BlogWebpageTabsEnum::navigation()
                ],
                'root_active' => $subNavigationRoot,

                BlogWebpageTabsEnum::SHOWCASE->value => $this->tab == BlogWebpageTabsEnum::SHOWCASE->value ?
                    fn () => WebpageResource::make($webpage)->getArray()
                    : Inertia::lazy(fn () => WebpageResource::make($webpage)->getArray()),
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Webpage $webpage, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Blogs')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $webpage->code,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };


        $webpage = Webpage::where('slug', $routeParameters['webpage'])->first();
        /** @var Website $website */
        $website = request()->route()->parameter('website');

        return match ($routeName) {
            'grp.org.shops.show.web.blogs.show', 'grp.org.shops.show.web.webpages.edit', 'grp.org.shops.show.web.blogs.workshop', 'grp.org.shops.show.web.webpages.redirect.create', 'grp.org.shops.show.web.blogs.edit' => array_merge(
                ShowWebsite::make()->getBreadcrumbs(
                    $website,
                    'grp.org.shops.show.web.websites.show',
                    Arr::only($routeParameters, ['organisation', 'shop', 'website'])
                ),
                $headCrumb(
                    $webpage,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.web.blogs.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'website'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.web.blogs.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'website', 'webpage'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => [],
        };
    }
}

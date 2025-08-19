<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:35:41 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Snapshot\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\UI\WithInertia;
use App\Actions\Web\Webpage\UI\ShowWebpage;
use App\Enums\UI\Web\WebpageTabsEnum;
use App\Http\Resources\Helpers\SnapshotResource;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Helpers\Snapshot;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use PhpOffice\PhpSpreadsheet\Calculation\Web;

class ShowSnapshot extends OrgAction
{
    use AsAction;
    use WithInertia;
    use WithWebAuthorisation;
    // use WithWebpageSubNavigation;
    private Webpage $webpage;

    public function asController(Organisation $organisation, Shop $shop, Website $website, Webpage $webpage, Snapshot $snapshot, ActionRequest $request): Snapshot
    {
        $this->webpage = $webpage;
        $this->initialisationFromShop($shop, $request);

        return $snapshot;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Webpage $webpage, Snapshot $snapshot, ActionRequest $request): Snapshot
    {
        $this->webpage = $webpage;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(WebpageTabsEnum::values());

        return $snapshot;
    }

    public function htmlResponse(Snapshot $snapshot, ActionRequest $request): Response
    {
        // $subNavigation = $this->getWebpageNavigation($snapshot->parent->website);
        $actions = [];
        return Inertia::render(
            'Org/Web/SnapshotWebpageShowcase',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('snapshot'),
                'pageHead'    => [
                    'title'         => $snapshot->label ?? __('Snapshot ').$snapshot->parent->code,
                    'afterTitle'    => [
                        'label' => '../'.$snapshot->parent->url,
                    ],
                    'icon'          => [
                        'title' => __('snapshot'),
                        'icon'  => 'fal fa-browser'
                    ],
                    'actions'       => $actions,
                ],
                'data' => SnapshotResource::make($snapshot)->resolve()
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Snapshot $snapshot, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Snapshots')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $snapshot->label ?? 'snapshot-'. $snapshot->published_at,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };


        $snapshot = Snapshot::where('id', $routeParameters['snapshot'])->first();
        /** @var Website $website */
        $website = request()->route()->parameter('website');

        return match ($routeName) {
            'grp.org.shops.show.web.webpages.snapshot.show' => array_merge(
                ShowWebpage::make()->getBreadcrumbs(
                    'grp.org.shops.show.web.webpages.snapshot.show',
                    Arr::only($routeParameters, ['organisation', 'shop', 'website', 'webpage'])
                ),
                $headCrumb(
                    $snapshot,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.web.webpages.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'website', 'webpage'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.web.webpages.snapshot.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'website', 'webpage', 'snapshot'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => [],
        };
    }
}

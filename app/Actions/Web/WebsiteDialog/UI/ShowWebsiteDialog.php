<?php

namespace App\Actions\Web\WebsiteDialog\UI;

use App\Actions\Helpers\Snapshot\UI\IndexSnapshots;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Enums\Web\WebsiteDialog\WebsiteDialogTabsEnum;
use App\Http\Resources\Helpers\SnapshotResource;
use App\Http\Resources\Web\WebsiteDialogResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use App\Actions\Web\WebsiteDialog\WithWebsiteDialogScope;
use Lorisleiva\Actions\ActionRequest;

class ShowWebsiteDialog extends OrgAction
{
    use WithWebsiteDialogScope;
    use WithWebAuthorisation;
    use WithWebsiteDialogNavigation;

    public function handle(WebsiteDialog $websiteDialog): WebsiteDialog
    {
        return $websiteDialog;
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, WebsiteDialog $websiteDialog, ActionRequest $request): WebsiteDialog
    {
        $this->ensureWebsiteDialogScope($shop, $website, $websiteDialog);
        $this->initialisationFromShop($shop, $request)->withTab(WebsiteDialogTabsEnum::values());

        return $this->handle($websiteDialog);
    }

    public function htmlResponse(WebsiteDialog $websiteDialog, ActionRequest $request): Response
    {
        $routeParameters = array_values($request->route()->originalParameters());

        $workshopRoute = [
            'name'       => 'grp.org.shops.show.web.website_dialogs.workshop',
            'parameters' => $routeParameters
        ];

        $showcase = fn () => array_merge(
            WebsiteDialogResource::make($websiteDialog)->getArray(),
            [
                'workshop_route' => $this->canEdit ? $workshopRoute : null
            ]
        );

        $snapshots = fn () => SnapshotResource::collection(
            IndexSnapshots::run(
                parent: $websiteDialog,
                prefix: WebsiteDialogTabsEnum::SNAPSHOTS->value
            )
        );

        return Inertia::render(
            'Websites/WebsiteDialog',
            [
                'title'      => $websiteDialog->name,
                'navigation' => [
                    'previous' => $this->getPreviousModel($websiteDialog, $request),
                    'next'     => $this->getNextModel($websiteDialog, $request),
                ],
                'breadcrumbs' => $this->getBreadcrumbs($websiteDialog, $request->route()->originalParameters()),
                'pageHead'   => [
                    'title'       => $websiteDialog->name,
                    'model'       => __('Dialog'),
                    'icon'        => [
                        'tooltip' => __('Dialog'),
                        'icon'    => 'fal fa-window-restore'
                    ],
                    'iconRight'   => $websiteDialog->status->statusIcon()[$websiteDialog->status->value],
                    'actions'     => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.website_dialogs.edit',
                                'parameters' => $routeParameters
                            ]
                        ] : false,
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'primary',
                            'label' => __('Workshop'),
                            'icon'  => ['fal', 'fa-drafting-compass'],
                            'route' => $workshopRoute
                        ] : false,
                    ],
                ],
                'tabs'       => [
                    'current'    => $this->tab,
                    'navigation' => WebsiteDialogTabsEnum::navigation()
                ],

                WebsiteDialogTabsEnum::SHOWCASE->value => $this->tab == WebsiteDialogTabsEnum::SHOWCASE->value
                    ? $showcase
                    : Inertia::optional($showcase),

                WebsiteDialogTabsEnum::SNAPSHOTS->value => $this->tab == WebsiteDialogTabsEnum::SNAPSHOTS->value
                    ? $snapshots
                    : Inertia::optional($snapshots),
            ]
        )->table(
            IndexSnapshots::make()->tableStructure(
                parent: $websiteDialog,
                prefix: WebsiteDialogTabsEnum::SNAPSHOTS->value
            )
        );
    }

    public function getBreadcrumbs(WebsiteDialog $websiteDialog, array $routeParameters, ?string $suffix = null): array
    {
        $parameters = Arr::only($routeParameters, ['organisation', 'shop', 'website']);

        return array_merge(
            ShowWebsite::make()->getBreadcrumbs(
                $websiteDialog->website,
                'grp.org.shops.show.web.websites.show',
                $routeParameters
            ),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.website_dialogs.index',
                                'parameters' => $parameters
                            ],
                            'label' => __('Dialogs'),
                            'icon'  => 'fal fa-bars'
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.website_dialogs.show',
                                'parameters' => [...$parameters, 'websiteDialog' => $websiteDialog->ulid]
                            ],
                            'label' => $websiteDialog->name,
                            'icon'  => 'fal fa-window-restore'
                        ],
                    ],
                    'suffix'         => $suffix
                ],
            ]
        );
    }
}

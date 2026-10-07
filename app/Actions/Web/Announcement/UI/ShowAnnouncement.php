<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement\UI;

use App\Actions\Helpers\Snapshot\UI\IndexSnapshots;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Enums\Announcement\AnnouncementTabsEnum;
use App\Http\Resources\Helpers\SnapshotResource;
use App\Http\Resources\Web\AnnouncementResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Announcement;
use App\Models\Web\Website;
use App\Actions\Web\Website\UI\ShowWebsite;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAnnouncement extends OrgAction
{
    use WithActionButtons;
    use WithWebAuthorisation;
    use WithAnnouncementNavigation;

    private Website $parent;

    public function handle(Announcement $announcement): Announcement
    {
        return $announcement;
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, Announcement $announcement, ActionRequest $request): Announcement
    {
        $this->parent = $website;
        $this->initialisationFromShop($shop, $request)->withTab(AnnouncementTabsEnum::values());

        return $this->handle($announcement);
    }

    public function htmlResponse(Announcement $announcement, ActionRequest $request): Response
    {
        $workshopRoute = [
            'name'       => preg_replace('/show$/', 'workshop', $request->route()->getName()),
            'parameters' => array_values($request->route()->originalParameters())
        ];

        $showcase = fn () => array_merge(
            AnnouncementResource::make($announcement)->getArray(),
            [
                'workshop_route' => $this->canEdit ? $workshopRoute : null
            ]
        );

        return Inertia::render(
            'Websites/Announcement',
            [
                'title'       => $announcement->name,
                'navigation'  => [
                    'previous' => $this->getPreviousModel($announcement, $request),
                    'next'     => $this->getNextModel($announcement, $request),
                ],
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'     => $announcement->name,
                    'icon'      => [
                        'tooltip' => __('announcement'),
                        'icon'    => 'fal fa-megaphone'
                    ],
                    "model" => __('Announcement'),
                    'iconRight' => $announcement->status->statusIcon()[$announcement->status->value],
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.announcements.edit',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ],
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'primary',
                            'label' => __('Workshop'),
                            'icon'  => ["fal", "fa-drafting-compass"],
                            'route' => $workshopRoute
                        ] : false,
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => AnnouncementTabsEnum::navigation()
                ],

                AnnouncementTabsEnum::SHOWCASE->value => $this->tab == AnnouncementTabsEnum::SHOWCASE->value
                    ?
                    fn () => $showcase()
                    : Inertia::optional(
                        fn () => $showcase()
                    ),

                AnnouncementTabsEnum::SNAPSHOTS->value => $this->tab == AnnouncementTabsEnum::SNAPSHOTS->value
                    ?
                    fn () => SnapshotResource::collection(
                        IndexSnapshots::run(
                            parent: $announcement,
                            prefix: AnnouncementTabsEnum::SNAPSHOTS->value
                        )
                    )
                    : Inertia::optional(fn () => SnapshotResource::collection(
                        IndexSnapshots::run(
                            parent: $announcement,
                            prefix: AnnouncementTabsEnum::SNAPSHOTS->value
                        )
                    )),
            ]
        )->table(
            IndexSnapshots::make()->tableStructure(
                parent: $announcement,
                prefix: AnnouncementTabsEnum::SNAPSHOTS->value
            )
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        /** @var Website $website */
        $website      = request()->route()->parameter('website');
        $announcement = Announcement::firstWhere('ulid', $routeParameters['announcement']);
        $parameters   = Arr::only($routeParameters, ['organisation', 'shop', 'website']);

        return array_merge(
            ShowWebsite::make()->getBreadcrumbs(
                $website,
                'grp.org.shops.show.web.websites.show',
                $routeParameters
            ),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.announcements.index',
                                'parameters' => $parameters
                            ],
                            'label' => __('Announcements'),
                            'icon'  => 'fal fa-bars'
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.announcements.show',
                                'parameters' => [...$parameters, 'announcement' => $routeParameters['announcement']]
                            ],
                            'label' => $announcement?->name,
                            'icon'  => 'fal fa-megaphone'
                        ],
                    ],
                    'suffix'         => $suffix
                ],
            ]
        );
    }
}

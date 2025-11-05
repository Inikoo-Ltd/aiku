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
use App\Models\Announcement;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAnnouncement extends OrgAction
{
    use WithActionButtons;
    use WithWebAuthorisation;

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
        return Inertia::render(
            'Websites/Announcement',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $announcement->name,
                'pageHead'    => [
                    'title'     => $announcement->name,
                    'icon'      => [
                        'tooltip' => __('announcement'),
                        'icon'    => 'fal fa-megaphone'
                    ],
                    "model" => __('Announcement'),
                    'iconRight' => $announcement->state->stateIcon()[$announcement->state->value],
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
                            'route' => [
                                'name'       => preg_replace('/show$/', 'workshop', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => AnnouncementTabsEnum::navigation()
                ],

                'routes_list' => [
                    'publish_route' => [
                        'name'       => 'grp.models.shop.website.announcement.publish',
                        'parameters' => [
                            'shop' => $announcement->website->shop->slug,
                            'website' => $announcement->website->slug,
                            'announcement'     => $announcement->ulid
                        ],
                        'method'    => 'patch'
                    ],
                    'update_route' => [
                        'name'       => 'grp.models.shop.website.announcement.update',
                        'parameters' => [
                            'shop' => $announcement->website->shop->slug,
                            'website' => $announcement->website->slug,
                            'announcement'     => $announcement->ulid
                        ],
                        'method'    => 'patch'
                    ],
                    'reset_route' => [
                        'name'       => 'grp.models.shop.website.announcement.reset',
                        'parameters' => [
                            'shop' => $announcement->website->shop->slug,
                            'website' => $announcement->website->slug,
                            'announcement'     => $announcement->ulid
                        ],
                        'method'    => 'delete'
                    ],
                    'close_route' => [
                        'name'       => 'grp.models.shop.website.announcement.close',
                        'parameters' => [
                            'shop' => $announcement->website->shop->slug,
                            'website' => $announcement->website->slug,
                            'announcement'     => $announcement->ulid
                        ],
                        'method'    => 'patch'
                    ],
                    'start_route' => [
                        'name'       => 'grp.models.shop.website.announcement.start',
                        'parameters' => [
                            'shop' => $announcement->website->shop->slug,
                            'website' => $announcement->website->slug,
                            'announcement'     => $announcement->ulid
                        ],
                        'method'    => 'patch'
                    ],
                    'activated_route'     => [
                        'name'          => 'grp.models.shop.website.announcement.toggle',
                        'parameters'    => [
                            'shop' => $announcement->website->shop->slug,
                            'website' => $announcement->website->slug,
                            'announcement'     => $announcement->ulid
                        ],
                        'method'    => 'patch'
                    ],
                    'upload_image_route'     => [
                        'name'          => 'grp.models.shop.website.announcement.upload-images.store',
                        'parameters'    => [
                            'shop' => $announcement->website->shop->slug,
                            'website' => $announcement->portfolio_website_id
                        ],
                        'method'    => 'post'
                    ],
                    'delete_announcement_route'     => [
                        'name'          => 'grp.models.shop.website.announcement.delete',
                        'parameters'    => [
                            'shop' => $announcement->website->shop->slug,
                            'website' => $announcement->portfolio_website_id
                        ],
                        'method'    => 'delete'
                    ]
                ],

                AnnouncementTabsEnum::SHOWCASE->value => $this->tab == AnnouncementTabsEnum::SHOWCASE->value
                    ?
                    fn () => AnnouncementResource::make($announcement)->getArray()
                    : Inertia::lazy(
                        fn () => AnnouncementResource::make($announcement)->getArray()
                    ),

                AnnouncementTabsEnum::SNAPSHOTS->value => $this->tab == AnnouncementTabsEnum::SNAPSHOTS->value
                    ?
                    fn () => SnapshotResource::collection(
                        IndexSnapshots::run(
                            parent: $announcement,
                            prefix: AnnouncementTabsEnum::SNAPSHOTS->value
                        )
                    )
                    : Inertia::lazy(fn () => SnapshotResource::collection(
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

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (string $type, Announcement $announcement, array $routeParameters, string $suffix = null) {
            return [
                [
                    'type'           => $type,
                    'simple'         => [
                        'route' => $routeParameters['model'],
                        'label' => $announcement->name
                    ],
                    'suffix'         => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.web.announcements.show',
            =>
            array_merge(
                IndexAnnouncements::make()->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    'simple',
                    Announcement::firstWhere('ulid', $routeParameters['announcement']),
                    [
                        'model' => [
                            'name'       => 'grp.org.shops.show.web.announcements.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }
}

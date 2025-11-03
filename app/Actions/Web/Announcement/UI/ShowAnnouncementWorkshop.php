<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Http\Resources\Web\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAnnouncementWorkshop extends OrgAction
{
    use WithWebEditAuthorisation;

    private Website $parent;

    public function handler(Website $parent, Announcement $announcement): Announcement
    {
        $this->parent = $parent;

        return $announcement;
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, Announcement $announcement, ActionRequest $request): Announcement
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handler($website, $announcement);
    }

    public function htmlResponse(Announcement $announcement, ActionRequest $request): Response
    {
        return Inertia::render(
            'Websites/AnnouncementWorkshop',
            [
                'title'             => __("Announcement's workshop"),
                'breadcrumbs'       => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'        => [
                    'previous' => $this->getPrevious($announcement, $request),
                    'next'     => $this->getNext($announcement, $request),
                ],
                'pageHead'          => [

                    'title'     => __('Workshop'),
                    'container' => [
                        'icon'    => ['fal', 'fa-sign'],
                        'tooltip' => __('Announcement'),
                        'label'   => Str::possessive($announcement->name)
                    ],
                    'iconRight' =>
                        [
                            'icon'  => ['fal', 'drafting-compass'],
                            'title' => __("Announcement's workshop")
                        ],

                    'actionActualMethod' => 'patch',
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
                    ],
                ],
                'banner'            => AnnouncementResource::make($announcement)->getArray(),
                'website' => [
                    'name' => $announcement->website->name,
                    'url' => $announcement->website->getUrl(),
                ],
                'is_announcement_dirty'       => $announcement->is_dirty,
                'is_announcement_started'     => $this->isAnnouncementStarted($announcement),
                'is_announcement_closed'      => $this->isAnnouncementClosed($announcement),
                'portfolio_website'           => $announcement->website,
                'announcement_data'           => $announcement->toArray(),
                'is_announcement_published'   => $announcement->unpublishedSnapshot->state === SnapshotStateEnum::LIVE,
                'is_announcement_active'      => $announcement->status,
                'last_published_date'         => $announcement->ready_at,
                'routes_list' => [
                    'publish_route' => [
                        'name'       => 'grp.models.shop.website.announcement.publish',
                        'parameters' => [
                            'shop' => $announcement->website->shop_id,
                            'website' => $announcement->website_id,
                            'announcement'     => $announcement->id
                        ],
                        'method'    => 'patch'
                    ],
                    'update_route' => [
                        'name'       => 'grp.models.shop.website.announcement.update',
                        'parameters' => [
                            'shop' => $announcement->website->shop_id,
                            'website' => $announcement->website_id,
                            'announcement'     => $announcement->id
                        ],
                        'method'    => 'patch'
                    ],
                    'reset_route' => [
                        'name'       => 'grp.models.shop.website.announcement.reset',
                        'parameters' => [
                            'shop' => $announcement->website->shop_id,
                            'website' => $announcement->website_id,
                            'announcement'     => $announcement->id
                        ]
                    ],
                    'close_route' => [
                        'name'       => 'grp.models.shop.website.announcement.close',
                        'parameters' => [
                            'shop' => $announcement->website->shop_id,
                            'website' => $announcement->website_id,
                            'announcement'     => $announcement->id
                        ],
                        'method'    => 'patch'
                    ],
                    'start_route' => [
                        'name'       => 'grp.models.shop.website.announcement.start',
                        'parameters' => [
                            'shop' => $announcement->website->shop_id,
                            'website' => $announcement->website_id,
                            'announcement'     => $announcement->id
                        ],
                        'method'    => 'patch'
                    ],
                    'activated_route'     => [
                        'name'          => 'grp.models.shop.website.announcement.toggle',
                        'parameters'    => [
                            'shop' => $announcement->website->shop_id,
                            'website' => $announcement->website_id,
                            'announcement'     => $announcement->id
                        ],
                        'method'    => 'patch'
                    ],
                    'upload_image_route'     => [
                        'name'          => 'grp.models.shop.website.announcement.upload-images.store',
                        'parameters'    => [
                            'shop' => $announcement->website->shop_id,
                            'website' => $announcement->website_id
                        ],
                        'method'    => 'post'
                    ],
                    'delete_announcement_route'     => [
                        'name'          => 'grp.models.shop.website.announcement.delete',
                        'parameters'    => [
                            'shop' => $announcement->website->shop_id,
                            'website' => $announcement->website_id
                        ],
                        'method'    => 'delete'
                    ]
                ],
                'autoSaveRoute'     => [
                    // 'name'       => 'grp.models.banner.layout.update',
                    // 'parameters' => [
                    //     'banner'  => $announcement->id
                    // ]
                ],
                'publishRoute'      => [
                    // 'name'       => 'grp.models.banner.publish',
                    // 'parameters' => [
                    //     'banner' => $announcement->id
                    // ]
                ],
                'imagesUploadRoute' => [
                    // 'name'       => 'grp.models.banner.images.store',
                    // 'parameters' => [
                    //     'banner' => $announcement->id
                    // ]
                ],
                'galleryRoute'      => [
                    // 'stock_images'    => [
                    //     'name' => "grp.gallery.stock-images.banner.$announcement->type.index"
                    // ],
                    // 'uploaded_images' => [
                    //     'name' => 'grp.gallery.uploaded-images.banner.index'
                    // ]
                ],
                'is_announcement_dirty'       => $announcement->is_dirty,
                'is_announcement_started'     => $this->isAnnouncementStarted($announcement),
                'is_announcement_closed'      => $this->isAnnouncementClosed($announcement),
                'portfolio_website'           => $announcement->portfolioWebsite,
                'announcement_data'           => $announcement->toArray(),
                'is_announcement_published'   => $announcement->unpublishedSnapshot->state === SnapshotStateEnum::LIVE,  // TODO
                'is_announcement_active'      => $announcement->status,
                'last_published_date'         => $announcement->ready_at,
            ]
        );
    }

    /**
     * Determine if the announcement has started.
     */
    public function isAnnouncementStarted($announcement): bool
    {
        if (! $announcement->live_at) {
            return false;
        }

        if (!$announcement->schedule_finish_at) {
            return true;
        }

        if ($announcement->live_at->lessThan(now()) && !$announcement->schedule_at) {
            return true;
        }

        return $announcement->live_at->lessThan(now()) || now()->between($announcement->schedule_finish_at, $announcement->schedule_at);
    }

    /**
     * Determine if the announcement is closed.
     */
    public function isAnnouncementClosed($announcement): bool
    {
        if (! $announcement->live_at) {
            return true;
        }

        if (!$announcement->closed_at) {
            return false;
        }

        return !$announcement->live_at->lessThan(now()) || now()->isAfter($announcement->closed_at);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowAnnouncement::make()->getBreadcrumbs(
            preg_replace('/workshop$/', 'show', $routeName),
            $routeParameters,
            '('.__('Workshop').')'
        );
    }

    public function getPrevious(Announcement $announcement, ActionRequest $request): ?array
    {
        $previous = Announcement::where('ulid', '<', $announcement->ulid)->orderBy('ulid', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName(), $request->route()->parameters);
    }

    public function getNext(Announcement $announcement, ActionRequest $request): ?array
    {
        $next = Announcement::where('ulid', '>', $announcement->ulid)->orderBy('ulid')->first();

        return $this->getNavigation($next, $request->route()->getName(), $request->route()->parameters);
    }

    private function getNavigation(?Announcement $announcement, string $routeName, array $routeParameters): ?array
    {
        if (!$announcement) {
            return null;
        }

        return match ($routeName) {
            'grp.org.shops.show.web.announcements.workshop' => [
                'label' => $announcement->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ]
            ],
        };
    }

}

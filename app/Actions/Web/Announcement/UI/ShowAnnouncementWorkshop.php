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
                'announcement'            => AnnouncementResource::make($announcement)->getArray(),
                'routes_list' => [
                    // 'publish_route' => [
                    //     'name'       => 'customer.models.portfolio-website.announcement.publish',
                    //     'parameters' => [
                    //         'portfolioWebsite' => $announcement->portfolio_website_id,
                    //         'announcement'     => $announcement->id
                    //     ],
                    //     'method'    => 'patch'
                    // ],
                    // 'update_route' => [
                    //     'name'       => 'customer.models.portfolio-website.announcement.update',
                    //     'parameters' => [
                    //         'portfolioWebsite' => $announcement->portfolio_website_id,
                    //         'announcement'     => $announcement->id
                    //     ],
                    //     'method'    => 'patch'
                    // ],
                    // 'reset_route' => [
                    //     'name'       => 'customer.models.portfolio-website.announcement.reset',
                    //     'parameters' => [
                    //         'portfolioWebsite' => $announcement->portfolio_website_id,
                    //         'announcement'     => $announcement->id
                    //     ]
                    // ],
                    // 'close_route' => [
                    //     'name'       => 'customer.models.portfolio-website.announcement.close',
                    //     'parameters' => [
                    //         'portfolioWebsite' => $announcement->portfolio_website_id,
                    //         'announcement'     => $announcement->id
                    //     ],
                    //     'method'    => 'patch'
                    // ],
                    // 'start_route' => [
                    //     'name'       => 'customer.models.portfolio-website.announcement.start',
                    //     'parameters' => [
                    //         'portfolioWebsite' => $announcement->portfolio_website_id,
                    //         'announcement'     => $announcement->id
                    //     ],
                    //     'method'    => 'patch'
                    // ],
                    // 'activated_route'     => [
                    //     'name'          => 'customer.models.portfolio-website.announcement.toggle',
                    //     'parameters'    => [
                    //         'portfolioWebsite' => $announcement->portfolio_website_id,
                    //         'announcement'     => $announcement->id
                    //     ],
                    //     'method'    => 'patch'
                    // ],
                    // 'upload_image_route'     => [
                    //     'name'          => 'customer.models.portfolio-website.announcement.upload-images.store',
                    //     'parameters'    => [
                    //         'portfolioWebsite' => $announcement->portfolio_website_id
                    //     ],
                    //     'method'    => 'post'
                    // ],
                    // 'delete_announcement_route'     => [
                    //     'name'          => 'customer.models.portfolio-website.announcement.delete',
                    //     'parameters'    => [
                    //         'portfolioWebsite' => $announcement->portfolio_website_id
                    //     ],
                    //     'method'    => 'delete'
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

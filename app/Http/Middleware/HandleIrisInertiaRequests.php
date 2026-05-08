<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 21 Sept 2022 01:57:29 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Web\Announcement;
use App\Models\Web\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleIrisInertiaRequests extends Middleware
{
    use WithIrisInertia;

    protected $rootView = 'app-iris';


    public function share(Request $request): array
    {
        $website  = $request->input('website');
        $outBoxes = $website?->shop?->outboxes()
            ?->whereIn('code', [OutboxCodeEnum::OOS_NOTIFICATION])
            ->select('id', 'code', 'state')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->code->value => [
                    'id'    => $item->id,
                    'state' => $item->state,
                ],
            ])
            ->toArray() ?? [];


        $firstLoadOnlyProps = [];

        if (!$request->inertia() || Session::get('reloadLayout')) {
            $websiteTheme = Arr::get($website->published_layout, 'theme');

            $firstLoadOnlyProps = [
                'webpage_id'  => $website->id,
                'currency'    => $request->input('currency_data'),
                'environment' => app()->environment(),
                'ziggy'       => function () use ($request) {
                    return array_merge(new Ziggy('iris')->toArray(), [
                        'location' => $request->url()
                    ]);
                },

                'use_chat'      => $website->settings['enable_chat'] ?? false,
                'iris'          => $this->getIrisData($website),
                'announcements' => $this->getAnnouncements($website),

                "retina"   => [
                    "type"         => $request->input('shop_type'),
                    "organisation" => $website->organisation->slug,
                ],
                "layout"   => [
                    "app_theme" => Arr::get($websiteTheme, 'color'),
                ],
                'outboxes' => $outBoxes
            ];


            if (Session::get('reloadLayout') == 'remove') {
                Session::forget('reloadLayout');
            }
            if (Session::get('reloadLayout')) {
                Session::put('reloadLayout', 'remove');
            }
        }


        return array_merge(
            $firstLoadOnlyProps,
            [
                'flash' => [
                    'notification' => fn () => $request->session()->get('notification'),
                    'modal'        => fn () => $request->session()->get('modal')
                ],
                'ziggy' => [
                    'location' => $request->url(),
                ],

            ],
            parent::share($request),
        );
    }

    public function getAnnouncements(Website $website): array
    {
        $announcements = [];
        /** @var Announcement $announcement */
        foreach ($website->announcements()->where('status', AnnouncementStatusEnum::ACTIVE)->get() as $announcement) {
            $extractedSettings = $announcement->extractSettings($announcement->settings);

            $announcements[] = [
                'ulid'                 => $announcement->ulid,
                'code'                 => $announcement->code,
                'name'                 => $announcement->name,
                'status'               => $announcement->status->statusIcon()[$announcement->status->value],
                'state_icon'           => $announcement->state->stateIcon()[$announcement->state->value],
                'show_pages'           => $extractedSettings['show_pages'],
                'hide_pages'           => $extractedSettings['hide_pages'],
                'container_properties' => $announcement->container_properties,
                'created_at'           => $announcement->created_at,
                'fields'               => $announcement->fields,
                'id'                   => $announcement->id,
                'icon'                 => $announcement->icon,
                'schedule_at'          => $announcement->schedule_at,
                'schedule_finish_at'   => $announcement->schedule_finish_at,
                'settings'             => $announcement->settings,
                'state'                => $announcement->state,
                'template_code'        => $announcement->template_code,
                'ready_at'             => $announcement->ready_at
            ];
        }

        return $announcements;
    }
}

<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 21 Sept 2022 01:57:29 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Web\Announcement\UI\GetIrisAnnouncements;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
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
                'currency'    => $request->input('currency_data'),
                'environment' => app()->environment(),
                'ziggy'       => function () use ($request) {
                    return array_merge((new Ziggy('iris'))->toArray(), [
                        'location' => $request->url(),
                    ]);
                },

                'use_chat' => $website->settings['enable_chat'] ?? false,
                'iris'     => array_merge($this->getIrisData($website), [
                    'google' => [
                        'client_id' => config('services.google.client_id'),
                    ],
                ]),
                'retina'        => [
                    'type'         => $request->input('shop_type'),
                    'organisation' => $website?->organisation?->slug,
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


        $alwaysProps = [
            'flash'         => [
                'notification' => fn () => $request->session()->get('notification'),
                'modal'        => fn () => $request->session()->get('modal')
            ],
            'announcements' => $website ? $this->getAnnouncements($website) : [],
            'show_contact_options_panel' => Arr::get($website?->settings ?? [], 'view_contact_options_panel', false),
            'contact_options_panel'      => Arr::get($website?->settings ?? [], 'data_contact_options_panel', []),
        ];

        if (!array_key_exists('ziggy', $firstLoadOnlyProps)) {
            $alwaysProps['ziggy'] = [
                'location' => $request->url(),
            ];
        }

        return array_merge(
            $firstLoadOnlyProps,
            $alwaysProps,
            parent::share($request),
        );
    }

    public function getAnnouncements(Website $website): array
    {
        return GetIrisAnnouncements::run($website);
    }

    public function getAnnouncementsCacheTtl(Website $website): int
    {
        return GetIrisAnnouncements::make()->getCacheTtl($website);
    }
}

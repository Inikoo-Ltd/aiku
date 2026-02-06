<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 21 Sept 2022 01:57:29 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use Carbon\Carbon;

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

                'use_chat' => $website->settings['enable_chat'] ?? false,
                'chat_config' => (function () use ($website) {
                    $shop = $website->shop;
                    $chatEnabled = $website->settings['enable_chat'] ?? false;
                    if (!$chatEnabled || !$shop) {
                        return ['is_online' => false, 'schedule' => null];
                    }

                    $effective = $shop->getEffectiveWorkSchedule();

                    $schedule = $effective['schedule'];
                    $timezone = $effective['timezone'];

                    $isOnline = false;
                    $todayHours = null;

                    if ($schedule) {
                        $isOnline = $schedule->isOpenNow($timezone);

                        $now = Carbon::now($timezone);
                        $dayOfWeek = $now->dayOfWeekIso;
                        $todaySchedule = $schedule->days->where('day_of_week', $dayOfWeek)->first();

                        if ($todaySchedule && $todaySchedule->is_working_day) {
                            $todayHours = [
                                'start' => $todaySchedule->start_time,
                                'end'   => $todaySchedule->end_time,
                                'timezone' => $timezone
                            ];
                        }
                    }

                    return [
                        'is_online' => $isOnline,
                        'schedule'  => $todayHours
                    ];
                })(),
                'iris'     => $this->getIrisData($website),
                "retina"   => [
                    "type" => $request->input('shop_type'),
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
}

<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 11 Aug 2022 18:11:19 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Http\Middleware;

use App\Actions\SysAdmin\User\UI\GetLoggedUser;
use App\Actions\UI\AikuPublic\BlogPosts;
use App\Actions\UI\Grp\GetFirstLoadProps;
use App\Models\SysAdmin\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;

class HandleInertiaGrpRequests extends Middleware
{
    protected $rootView = 'app-grp';

    /**
     * JSON endpoints outside grp.json.* polled from every open tab: the full layout is never read from their response.
     */
    private const array JSON_ONLY_ROUTES = [
        'grp.chat.staff.coworkers.index',
        'grp.chat.staff.conversations.index',
        'grp.chat.presence.track',
        'grp.chat.staff.gifs.search',
        'grp.chat.staff.conversations.messages.index',
        'grp.search.index',
        'grp.org.productions.show.queue_counts',
        'grp.org.shops.show.dashboard.widgets',
        'grp.org.chat.dashboard-visitors',
        'grp.org.shops.show.chat.dashboard-visitors',
        'grp.org.fulfilments.show.chat.dashboard-visitors',
        'grp.models.work-schedule.index',
        'grp.models.clocking-machine.qr.validate',
        'grp.models.clocking-machine.clocking.notes.update',
        'grp.models.translate',
        'grp.models.delivery_note.state.packed',
        'grp.models.printing.shipment.label',
    ];

    public function share(Request $request): array
    {
        $routeName = $request->route()->getName();
        if (str_starts_with($routeName, 'grp.json.') || in_array($routeName, self::JSON_ONLY_ROUTES, true)) {
            return [];
        }

        /** @var User $user */
        $user = $request->user();

        $firstLoadOnlyProps = [];


        if (!$request->inertia() || Session::get('reloadLayout')) {
            $firstLoadOnlyProps          = GetFirstLoadProps::run($user);
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
                'auth'  => [
                    'user' => $request->user() ? GetLoggedUser::run($request->user()) : null,
                ],
                'flash' => [
                    'notification' => fn () => $request->session()->get('notification'),
                    'modal'        => fn () => $request->session()->get('modal')
                ],
                'help' => fn () => BlogPosts::helpFor($routeName, $user?->language?->code),
                'ziggy' => [
                    'location' => $request->url(),
                ],
                'phpComponent' => app()->environment('local') ? str_replace('\\', '/', $request->route()->getActionName()) : null,

            ],
            parent::share($request),
        );
    }
}

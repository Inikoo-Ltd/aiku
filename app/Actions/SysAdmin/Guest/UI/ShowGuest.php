<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest\UI;

use App\Actions\GrpAction;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\SysAdmin\Guest\WithGuestSubNavigations;
use App\Actions\SysAdmin\UI\ShowSysAdminDashboard;
use App\Enums\UI\SysAdmin\GuestTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\SysAdmin\GuestResource;
use App\Models\SysAdmin\Guest;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowGuest extends GrpAction
{
    use WithGuestSubNavigations;

    public function asController(Guest $guest, ActionRequest $request): Guest
    {
        $this->initialisation(app('group'), $request)->withTab(GuestTabsEnum::values());

        return $guest;
    }

    public function jsonResponse(Guest $guest): GuestResource
    {
        return new GuestResource($guest);
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo('sysadmin.users.edit');
        $this->canDelete = $request->user()->authTo('sysadmin.users.edit');
        return $request->user()->authTo("sysadmin.view");
    }

    public function htmlResponse(Guest $guest, ActionRequest $request): Response
    {
        return Inertia::render(
            'SysAdmin/Guest',
            [
                'title'       => __('guest'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($guest, $request),
                    'next'     => $this->getNext($guest, $request),
                ],
                'pageHead'    => [
                    'subNavigation' => $this->getGuestNavigation($guest, $request),
                    'title'     => $guest->contact_name,
                    'model'     => __('guest'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-user-alien'],
                        'title' => __('Guest')
                    ],
                    'actions'   => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => GuestTabsEnum::navigation()
                ],
                GuestTabsEnum::SHOWCASE->value => $this->tab == GuestTabsEnum::SHOWCASE->value ?
                    fn () => GetGuestShowcase::run($guest)
                    : Inertia::lazy(fn () => GetGuestShowcase::run($guest)),
                GuestTabsEnum::HISTORY->value => $this->tab == GuestTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($guest))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($guest)))
            ]
        )->table(IndexHistory::make()->tableStructure(prefix: GuestTabsEnum::HISTORY->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {

        $guest = Guest::where('slug', $routeParameters['guest'])->firstOrFail();

        $headCrumb = function (Guest $guest, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Guests')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $guest->slug,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.sysadmin.guests.show',
            'grp.sysadmin.guests.edit' =>

            array_merge(
                ShowSysAdminDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $guest,
                    [
                        'index' => [
                            'name'       => 'grp.sysadmin.guests.index',
                            'parameters' => []
                        ],
                        'model' => [
                            'name'       => 'grp.sysadmin.guests.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),

            default => []
        };
    }

    public function getPrevious(Guest $guest, ActionRequest $request): ?array
    {
        $previous = Guest::where('slug', '<', $guest->slug)->orderBy('slug', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(Guest $guest, ActionRequest $request): ?array
    {
        $next = Guest::where('slug', '>', $guest->slug)->orderBy('slug')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Guest $guest, string $routeName): ?array
    {
        if (!$guest) {
            return null;
        }
        return match ($routeName) {
            'grp.sysadmin.guests.show' => [
                'label' => $guest->contact_name,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'guest' => $guest->slug
                    ]

                ]
            ]
        };
    }

}

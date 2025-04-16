<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:08:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail\UI;

use App\Actions\Comms\EmailTrackingEvent\UI\IndexEmailTrackingEvents;
use App\Actions\Comms\Outbox\UI\ShowOutbox;
use App\Actions\OrgAction;
use App\Enums\UI\Mail\DispatchedEmailTabsEnum;
use App\Http\Resources\Mail\EmailTrackingEventResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Outbox;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property DispatchedEmail $dispatchedEmail
 */
class ShowDispatchedEmail extends OrgAction
{
    private Fulfilment|Shop|Group $parent;

    public function handle(DispatchedEmail $dispatchedEmail): DispatchedEmail
    {
        return $dispatchedEmail;
    }

    public function authorize(ActionRequest $request): bool
    {

        if ($this->parent instanceof Fulfilment) {
            return    $this->canEdit = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        }

        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        return $request->user()->authTo([
            'shop-admin.'.$this->shop->id,
            'marketing.'.$this->shop->id.'.view',
            'web.'.$this->shop->id.'.view',
            'orders.'.$this->shop->id.'.view',
            'crm.'.$this->shop->id.'.view',
        ]);
    }

    public function inOutboxInFulfilment(Organisation $organisation, Fulfilment $fulfilment, Outbox $outbox, DispatchedEmail $dispatchedEmail, ActionRequest $request): DispatchedEmail
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(DispatchedEmailTabsEnum::values());
        return $this->handle($dispatchedEmail);
    }

    public function inOutboxInShop(Organisation $organisation, Shop $shop, Outbox $outbox, DispatchedEmail $dispatchedEmail, ActionRequest $request): DispatchedEmail
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(DispatchedEmailTabsEnum::values());
        return $this->handle($dispatchedEmail);
    }

    public function htmlResponse(DispatchedEmail $dispatchedEmail, ActionRequest $request): Response
    {
        return Inertia::render(
            'Comms/DispatchedEmail',
            [
                'title'       => $dispatchedEmail->id,
                'breadcrumbs' => $this->getBreadcrumbs($dispatchedEmail, $request->route()->getName(), $request->route()->originalParameters()),
                'pageHead'    => [
                    'icon'  => [
                        'icon' => 'fal fa-paper-plane',
                        'title' => __('Dispatched Email')
                    ],
                    'model'     => __('Dispatched Email'),
                    'title' => $dispatchedEmail->id,

                ],

                'tabs'             => [
                    'current'    => $this->tab,
                    'navigation' => DispatchedEmailTabsEnum::navigation()

                ],


                DispatchedEmailTabsEnum::EMAIL_TRACKING_EVENTS->value => $this->tab == DispatchedEmailTabsEnum::EMAIL_TRACKING_EVENTS->value ?
                    fn () => EmailTrackingEventResource::collection(IndexEmailTrackingEvents::run($dispatchedEmail, DispatchedEmailTabsEnum::EMAIL_TRACKING_EVENTS->value))
                    : Inertia::lazy(fn () => EmailTrackingEventResource::collection(IndexEmailTrackingEvents::run($dispatchedEmail, DispatchedEmailTabsEnum::EMAIL_TRACKING_EVENTS->value))),

            ]
        )->table(IndexEmailTrackingEvents::make()->tableStructure($dispatchedEmail, DispatchedEmailTabsEnum::EMAIL_TRACKING_EVENTS->value));
    }

    public function getBreadcrumbs(DispatchedEmail $dispatchedEmail, string $routeName, array $routeParameters, string $suffix = ''): array
    {

        return match ($routeName) {
            'grp.org.fulfilments.show.operations.comms.outboxes.dispatched-email.show',
            => array_merge(
                ShowOutbox::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $routeParameters
                            ],
                            'label' => $dispatchedEmail->id
                        ]
                    ]
                ]
            ),
            'grp.org.shops.show.dashboard.comms.outboxes.dispatched-email.show',
            => array_merge(
                ShowOutbox::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $routeParameters
                            ],
                            'label' => $dispatchedEmail->id
                        ]
                    ]
                ]
            ),
            default => []
        };
    }
}

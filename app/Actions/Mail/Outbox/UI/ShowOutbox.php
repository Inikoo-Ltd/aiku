<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jun 2024 22:35:44 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Mail\Outbox\UI;

use App\Actions\OrgAction;
use App\Actions\Web\HasWorkshopAction;
use App\Enums\UI\Mail\OutboxTabsEnum;
use App\Http\Resources\Mail\OutboxResource;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Mail\Outbox;
use App\Models\Mail\PostRoom;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property Outbox $outbox
 */
class ShowOutbox extends OrgAction
{
    //use HasUIOutbox;
    use HasWorkshopAction;
    public function handle(Outbox $outbox): Outbox
    {
        return $outbox;
    }

    // public function authorize(ActionRequest $request): bool
    // {
    //     $this->canEdit = $request->user()->hasPermissionTo('mail.edit');
    //     return $request->user()->hasPermissionTo('marketing.view');
    // }

    public function inOrganisation(Organisation $organisation, Outbox $outbox, ActionRequest $request): Outbox
    {

        $this->initialisation($organisation, $request);
        return $this->handle($outbox);
    }

    /** @noinspection PhpUnusedParameterInspection */
    // public function inPostRoom(PostRoom $postRoom, Outbox $outbox, ActionRequest $request): Outbox
    // {

    //     $this->initialisation($request);
    //     return $this->handle($outbox);
    // }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, Outbox $outbox, ActionRequest $request): Outbox
    {

        $this->initialisationFromShop($shop, $request);
        return $this->handle($outbox);
    }

    public function inWebsite(Organisation $organisation, Shop $shop, Website $website, Outbox $outbox, ActionRequest $request): Outbox
    {

        $this->initialisationFromShop($shop, $request);
        return $this->handle($outbox);
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Outbox $outbox, ActionRequest $request): Outbox
    {

        $this->initialisationFromFulfilment($fulfilment, $request);
        return $this->handle($outbox);
    }

    /** @noinspection PhpUnusedParameterInspection */
    // public function inPostRoomInShop(PostRoom $postRoom, Outbox $outbox, ActionRequest $request): Outbox
    // {

    //     $this->initialisation($request);
    //     return $this->handle($outbox);
    // }

    public function htmlResponse(Outbox $outbox, ActionRequest $request): Response
    {
        $this->canEdit = true;
        $actions       = $this->workshopActions($request);
        return Inertia::render(
            'Mail/Outbox',
            [
                'title'       => __('outbox'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($outbox, $request),
                    'next'     => $this->getNext($outbox, $request),
                ],
                'pageHead'    => [
                    'title'     => $outbox->slug,
                    'model'     => __('outbox'),
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('outbox')
                        ],
                'actions' => $actions,
                ],
                'tabs'=> [
                    'current'    => $this->tab,
                    'navigation' => OutboxTabsEnum::navigation()
                ],


                OutboxTabsEnum::SHOWCASE->value => $this->tab == OutboxTabsEnum::SHOWCASE->value ?
                    fn () => GetOutboxShowcase::run($outbox)
                    : Inertia::lazy(fn () => GetOutboxShowcase::run($outbox)),


            ]
        );
    }


    public function jsonResponse(Outbox $outbox): OutboxResource
    {
        return new OutboxResource($outbox);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Outbox $outbox, array $routeParameters, $suffix) {
            return [
                [
                   'type'   => 'simple',
                   'simple' => [
                       'route' => $routeParameters,
                       'label' => $outbox->slug,
                   ],
           ],
        ];
        };

        $outbox = Outbox::where('slug', $routeParameters['outbox'])->first();

        return match ($routeName) {
            'grp.org.shops.show.mail.outboxes.show' =>
            array_merge(
                IndexOutboxes::make()->getBreadcrumbs('grp.org.shops.show.mail.outboxes', $routeParameters),
                $headCrumb(
                    $outbox,
                    [

                            'name'       => 'grp.org.shops.show.mail.outboxes.show',
                            'parameters' => $routeParameters

                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.web.websites.outboxes.show' =>
            array_merge(
                IndexOutboxes::make()->getBreadcrumbs('grp.org.shops.show.web.websites.outboxes', $routeParameters),
                $headCrumb(
                    $outbox,
                    [

                            'name'       => 'grp.org.shops.show.web.websites.outboxes.show',
                            'parameters' => $routeParameters

                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(Outbox $outbox, ActionRequest $request): ?array
    {
        $previous = Outbox::where('slug', '<', $outbox->slug)->orderBy('slug', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(Outbox $outbox, ActionRequest $request): ?array
    {
        $next = Outbox::where('slug', '>', $outbox->slug)->orderBy('slug')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Outbox $outbox, string $routeName): ?array
    {
        if (!$outbox) {
            return null;
        }

        return match ($routeName) {
            'grp.org.shops.show.mail.outboxes.show'=> [
                'label'=> $outbox->name,
                'route'=> [
                    'name'      => $routeName,
                    'parameters'=> [
                        'organisation'   => $this->organisation->slug,
                        'shop'           => $outbox->shop->slug,
                        'outbox'         => $outbox->slug
                    ]

                ]
            ],
            'grp.org.shops.show.web.websites.outboxes.show'=> [
                'label'=> $outbox->name,
                'route'=> [
                    'name'      => $routeName,
                    'parameters'=> [
                        'organisation'   => $this->organisation->slug,
                        'shop'           => $outbox->shop->slug,
                        'website'        => $outbox->website->slug,
                        'outbox'         => $outbox->slug
                    ]

                ]
            ],
        };
    }


}

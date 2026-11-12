<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappOutbox\UI;

use App\Actions\Comms\Outbox\UI\IndexOutboxes;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCommsAuthorisation;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\UI\Mail\WhatsappOutboxTabsEnum;
use App\Actions\Comms\WhatsappBulkRun\UI\IndexWhatsappBulkRuns;
use App\Http\Resources\Comms\WhatsappBulkRunsResource;
use App\Http\Resources\Comms\WhatsappOutboxMessagesResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappOutbox;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWhatsappOutbox extends OrgAction
{
    use WithCommsAuthorisation;

    public function handle(WhatsappOutbox $whatsappOutbox): WhatsappOutbox
    {
        return $whatsappOutbox;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, WhatsappOutbox $whatsappOutbox, ActionRequest $request): WhatsappOutbox
    {
        abort_unless($whatsappOutbox->shop_id === $shop->id, 404);
        $this->initialisationFromShop($shop, $request)->withTab(WhatsappOutboxTabsEnum::values());

        return $this->handle($whatsappOutbox);
    }

    public function htmlResponse(WhatsappOutbox $whatsappOutbox, ActionRequest $request): Response
    {
        return Inertia::render(
            'Comms/WhatsappOutbox',
            [
                'title'       => $whatsappOutbox->name,
                'breadcrumbs' => $this->getBreadcrumbs($whatsappOutbox, $request->route()->originalParameters()),
                'pageHead'    => [
                    'title'     => $whatsappOutbox->name,
                    'model'     => __('WhatsApp outbox'),
                    'icon'      => [
                        'icon'  => ['fab', 'fa-whatsapp'],
                        'title' => __('WhatsApp outbox'),
                    ],
                    'iconRight' => OutboxStateEnum::icon()[$whatsappOutbox->state->value],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $whatsappOutbox->isBulk()
                        ? WhatsappOutboxTabsEnum::navigation()
                        : WhatsappOutboxTabsEnum::navigationExcept([WhatsappOutboxTabsEnum::BULK_RUNS]),
                ],

                WhatsappOutboxTabsEnum::SHOWCASE->value => $this->tab == WhatsappOutboxTabsEnum::SHOWCASE->value
                    ? fn () => GetWhatsappOutboxShowcase::run($whatsappOutbox, $this->canEdit)
                    : Inertia::optional(fn () => GetWhatsappOutboxShowcase::run($whatsappOutbox, $this->canEdit)),

                WhatsappOutboxTabsEnum::MESSAGES->value => $this->tab == WhatsappOutboxTabsEnum::MESSAGES->value
                    ? fn () => WhatsappOutboxMessagesResource::collection(IndexWhatsappOutboxMessages::run($whatsappOutbox, WhatsappOutboxTabsEnum::MESSAGES->value))
                    : Inertia::optional(fn () => WhatsappOutboxMessagesResource::collection(IndexWhatsappOutboxMessages::run($whatsappOutbox, WhatsappOutboxTabsEnum::MESSAGES->value))),

                WhatsappOutboxTabsEnum::BULK_RUNS->value => $this->tab == WhatsappOutboxTabsEnum::BULK_RUNS->value
                    ? fn () => WhatsappBulkRunsResource::collection(IndexWhatsappBulkRuns::run($whatsappOutbox, WhatsappOutboxTabsEnum::BULK_RUNS->value))
                    : Inertia::optional(fn () => WhatsappBulkRunsResource::collection(IndexWhatsappBulkRuns::run($whatsappOutbox, WhatsappOutboxTabsEnum::BULK_RUNS->value))),
            ]
        )->table(IndexWhatsappOutboxMessages::make()->tableStructure(WhatsappOutboxTabsEnum::MESSAGES->value))
            ->table(IndexWhatsappBulkRuns::make()->tableStructure(WhatsappOutboxTabsEnum::BULK_RUNS->value));
    }

    public function getBreadcrumbs(WhatsappOutbox $whatsappOutbox, array $routeParameters): array
    {
        $indexParameters = [
            'organisation' => $routeParameters['organisation'],
            'shop'         => $routeParameters['shop'],
        ];

        return array_merge(
            IndexOutboxes::make()->getBreadcrumbs('grp.org.shops.show.dashboard.comms.customer_notification_outboxes.index', $indexParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.dashboard.comms.customer_notification_outboxes.index',
                            'parameters' => $indexParameters + ['tab' => 'whatsapp'],
                        ],
                        'label' => __('WhatsApp'),
                    ],
                ],
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.dashboard.comms.whatsapp_outboxes.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $whatsappOutbox->name,
                    ],
                ],
            ]
        );
    }
}

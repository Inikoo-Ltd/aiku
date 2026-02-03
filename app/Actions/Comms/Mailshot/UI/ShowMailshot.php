<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\Comms\Mailshot\GetMailshotRecipientsQueryBuilder;
use App\Actions\Comms\MailshotRecipient\UI\IndexMailshotRecipients;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\UI\Marketing\MarketingHub;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\UI\Mail\MailshotTabsEnum;
use App\Http\Resources\Inventory\LocationResource;
use App\Http\Resources\Mail\DispatchedEmailsResource;
use App\Http\Resources\Comms\MailshotRecipient\MailshotRecipientsResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Web\Website;

/**
 * @property Mailshot $mailshot
 */
class ShowMailshot extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Mailshot $mailshot): Mailshot
    {
        return $mailshot;
    }


    public function asController(Organisation $organisation, Shop $shop, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request)->withTab(MailshotTabsEnum::values());

        return $this->handle($mailshot);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOutboxInWebsite(Organisation $organisation, Shop $shop, Website $website, Outbox $outbox, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request)->withTab(MailshotTabsEnum::values());

        return $this->handle($mailshot);
    }


    public function htmlResponse(Mailshot $mailshot, ActionRequest $request): Response
    {
        $isShowActions = $this->canEdit && in_array($mailshot->state, [MailshotStateEnum::IN_PROCESS, MailshotStateEnum::READY]);

        $estimatedRecipients = ($mailshot->type === MailshotTypeEnum::MARKETING && in_array($mailshot->state, [MailshotStateEnum::IN_PROCESS, MailshotStateEnum::READY, MailshotStateEnum::SCHEDULED]))
            ? (GetMailshotRecipientsQueryBuilder::make()->handle($mailshot)?->count() ?? 0)
            : 0;


        return Inertia::render(
            'Comms/Mailshot',
            [
                'title'                           => $mailshot->id,
                'breadcrumbs'                     => $this->getBreadcrumbs(
                    $mailshot,
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'pageHead'                        => [
                    'icon'    => 'fal fa-coins',
                    'title'   => $mailshot->type->value . ' ' . $mailshot->id,
                    'edit'    => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,
                    'actions' => [
                        $isShowActions & $mailshot->type === MailshotTypeEnum::MARKETING ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Set Up Recipients'),
                            'icon'  => ["fal", "fa-sliders-h"],
                            'route' => [
                                'name'       => "grp.org.shops.show.marketing.mailshots.recipients",
                                'parameters' => [
                                    $this->organisation->slug,
                                    $this->shop->slug,
                                    $mailshot->slug
                                ]
                            ]
                        ] : [],
                        $isShowActions ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Workshop'),
                            'icon'  => ["fal", "fa-drafting-compass"],
                            'route' => [
                                'name'       => $mailshot->type === MailshotTypeEnum::MARKETING ? "grp.org.shops.show.marketing.mailshots.workshop" : "grp.org.shops.show.marketing.newsletters.workshop",
                                'parameters' => [
                                    $this->organisation->slug,
                                    $this->shop->slug,
                                    $mailshot->slug
                                ]
                            ]
                        ] : [],
                        $isShowActions ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'icon'  => ["fal", "fa-sliders-h"],
                            'route' => [
                                'name'       => $mailshot->type === MailshotTypeEnum::MARKETING ? "grp.org.shops.show.marketing.mailshots.edit" : "grp.org.shops.show.marketing.newsletters.edit",
                                'parameters' => [
                                    $this->organisation->slug,
                                    $this->shop->slug,
                                    $mailshot->slug
                                ]
                            ]
                        ] : []
                    ]
                ],
                'tabs'                            => [
                    'current'    => $this->tab,
                    'navigation' => MailshotTabsEnum::navigation($mailshot)
                ],
                MailshotTabsEnum::SHOWCASE->value => $this->tab == MailshotTabsEnum::SHOWCASE->value ?
                    fn () => GetMailshotShowcase::run($mailshot)
                    : Inertia::lazy(fn () => GetMailshotShowcase::run($mailshot)),

                MailshotTabsEnum::RECIPIENTS->value => $this->tab == MailshotTabsEnum::RECIPIENTS->value ?
                    fn () => MailshotRecipientsResource::collection(IndexMailshotRecipients::run($mailshot, MailshotTabsEnum::RECIPIENTS->value))
                    : Inertia::lazy(fn () => MailshotRecipientsResource::collection(IndexMailshotRecipients::run($mailshot, MailshotTabsEnum::RECIPIENTS->value))),


                MailshotTabsEnum::DISPATCHED_EMAILS->value => $this->tab == MailshotTabsEnum::DISPATCHED_EMAILS->value
                    ?
                    fn () => DispatchedEmailsResource::collection(
                        IndexDispatchedEmails::run(
                            parent: $mailshot,
                            prefix: MailshotTabsEnum::DISPATCHED_EMAILS->value
                        )
                    )
                    : Inertia::lazy(fn () => LocationResource::collection(
                        IndexDispatchedEmails::run(
                            parent: $mailshot,
                            prefix: MailshotTabsEnum::DISPATCHED_EMAILS->value
                        )
                    )),
                'sendMailshotRoute' => [
                    'name' => match($mailshot->type) {
                        MailshotTypeEnum::NEWSLETTER => 'grp.models.shop.outboxes.newsletter.send',
                        MailshotTypeEnum::MARKETING => 'grp.models.shop.outboxes.mailshot.send',
                    },
                    'parameters' => [
                        'shop' => $this->shop->id,
                        'outbox' => $mailshot->outbox->id,
                        'mailshot' => $mailshot->id
                    ],
                ],
                'scheduleMailshotRoute' => [
                    'name' => match($mailshot->type) {
                        MailshotTypeEnum::NEWSLETTER => 'grp.models.shop.outboxes.newsletter.schedule',
                        MailshotTypeEnum::MARKETING => 'grp.models.shop.outboxes.mailshot.schedule',
                    },
                    'parameters' => [
                        'shop' => $this->shop->id,
                        'outbox' => $mailshot->outbox->id,
                        'mailshot' => $mailshot->id
                    ],
                ],
                'deleteMailshotRoute' => [
                    'name' => 'grp.models.shop.mailshot.delete',
                    'parameters' => [
                        'shop' => $this->shop->id,
                        'mailshot' => $mailshot->id
                    ],
                ],
                'indexRoute' => [
                    'name' => match($mailshot->type) {
                        MailshotTypeEnum::NEWSLETTER => 'grp.org.shops.show.marketing.newsletters.index',
                        MailshotTypeEnum::MARKETING => 'grp.org.shops.show.marketing.mailshot.index',
                    },
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop' => $this->shop->slug
                    ],
                ],
                'cancelScheduleMailshotRoute' => [
                    'name' => match($mailshot->type) {
                        MailshotTypeEnum::NEWSLETTER => 'grp.models.shop.outboxes.newsletter.cancel-schedule',
                        MailshotTypeEnum::MARKETING => 'grp.models.shop.outboxes.mailshot.cancel-schedule',
                    },
                    'parameters' => [
                        'shop' => $this->shop->id,
                        'outbox' => $mailshot->outbox->id,
                        'mailshot' => $mailshot->id
                    ],
                ],
                'status' => $mailshot->state->value,
                'estimatedRecipients' => $estimatedRecipients,
                'mailshotType' => $mailshot->type->value,
            ]
        )->table(
            IndexDispatchedEmails::make()->tableStructure(
                parent: $mailshot,
                prefix: MailshotTabsEnum::DISPATCHED_EMAILS->value
            )
        )->table(
            IndexMailshotRecipients::make()->tableStructure(
                parent: $mailshot,
                prefix: MailshotTabsEnum::RECIPIENTS->value
            )
        );
    }

    public function getBreadcrumbs(Mailshot $mailshot, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Mailshot $mailshot, array $routeParameters, string $suffix) {

            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => $mailshot->type == MailshotTypeEnum::NEWSLETTER ? __('Newsletters') : __('Mailshots')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $mailshot->subject,
                        ],

                    ],
                    'suffix'         => $suffix
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.marketing.mailshots.show' => array_merge(
                MarketingHub::make()->getBreadcrumbs(
                    'grp.marketing.shops.show.hub',
                    Arr::only($routeParameters, ['organisation', 'shop']),
                ),
                $headCrumb(
                    $mailshot,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.marketing.mailshots.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.marketing.mailshots.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'mailshot'])
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.marketing.newsletters.show' => array_merge(
                MarketingHub::make()->getBreadcrumbs(
                    'grp.marketing.shops.show.hub',
                    Arr::only($routeParameters, ['organisation', 'shop']),
                ),
                $headCrumb(
                    $mailshot,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.marketing.newsletters.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.marketing.newsletters.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'mailshot'])
                        ]
                    ],
                    $suffix
                )
            ),


            default => []
        };
    }
}

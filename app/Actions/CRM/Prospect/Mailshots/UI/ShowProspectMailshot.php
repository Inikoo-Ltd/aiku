<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 26 Feb 2026 16:38:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots\UI;

use App\Actions\Comms\Mailshot\UI\GetMailshotShowcase;
use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\Comms\Mailshot\GetMailshotRecipientsQueryBuilder;
use App\Actions\Comms\MailshotRecipient\UI\IndexMailshotRecipients;
use App\Actions\CRM\Prospect\UI\IndexProspects;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\WithProspectsSubNavigation;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\UI\Mail\MailshotTabsEnum;
use App\Http\Resources\Inventory\LocationResource;
use App\Http\Resources\Mail\DispatchedEmailsResource;
use App\Http\Resources\Comms\MailshotRecipient\MailshotRecipientsResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\CRM\Prospect;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property Mailshot $mailshot
 */
class ShowProspectMailshot extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithProspectsSubNavigation;

    public function handle(Mailshot $mailshot): Mailshot
    {
        return $mailshot;
    }

    public function asController(Organisation $organisation, Shop $shop, Prospect $prospect, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request)->withTab(MailshotTabsEnum::values());

        return $this->handle($mailshot);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOutboxInWebsite(Organisation $organisation, Shop $shop, Prospect $prospect, Website $website, Outbox $outbox, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request)->withTab(MailshotTabsEnum::values());

        return $this->handle($mailshot);
    }

    public function htmlResponse(Mailshot $mailshot, ActionRequest $request): Response
    {
        $isShowActions = $this->canEdit && in_array($mailshot->state, [MailshotStateEnum::IN_PROCESS, MailshotStateEnum::READY]) && !$mailshot->is_second_wave;

        $isShowStop = $this->canEdit && in_array($mailshot->state, [MailshotStateEnum::SENDING]);

        $isShowResume = $this->canEdit && in_array($mailshot->state, [MailshotStateEnum::STOPPED]);

        $estimatedRecipients = ($mailshot->type === MailshotTypeEnum::MARKETING && in_array($mailshot->state, [MailshotStateEnum::IN_PROCESS, MailshotStateEnum::READY, MailshotStateEnum::SCHEDULED]))
            ? (GetMailshotRecipientsQueryBuilder::make()->handle($mailshot)?->count() ?? 0)
            : 0;

        $isSecondWaveActive = $mailshot->secondWave()->exists() && $mailshot->is_second_wave_enabled;
        $mailshotSecondWave = null;
        if ($isSecondWaveActive) {
            $mailshotSecondWave = $mailshot->secondWave;
        }
        $isHasParentMailshot = $mailshot->parentMailshot()->exists();

        /* NOTE:
         * is_second_wave_enabled is perspective from parent mailshot
         * is_second_wave  is perspective from child mailshot
         */
        return Inertia::render(
            'Org/Shop/CRM/ProspectMailshot',
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
                    'subNavigation' => $this->getSubNavigation($this->shop, $request),
                    'edit'    => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,
                    'actions' => [
                        $isShowActions ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Set Up Recipients'),
                            'icon'  => ["fal", "fa-sliders-h"],
                            'route' => [
                                'name'       => "grp.org.shops.show.crm.prospects.mailshots.recipients",
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
                                'name'       => "grp.org.shops.show.crm.prospects.mailshots.workshop",
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
                                'name'       => "grp.org.shops.show.crm.prospects.mailshots.edit",
                                'parameters' => [
                                    $this->organisation->slug,
                                    $this->shop->slug,
                                    $mailshot->slug
                                ]
                            ]
                        ] : [],
                        $isShowStop ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Stop'),
                            'icon'  => ["fal", "fa-pause"],
                            'route' => [
                                'name'       => "grp.models.shop.mailshot.stop",
                                'parameters' => [
                                    $this->shop->id,
                                    $mailshot->id
                                ],
                                'method'     => 'post'
                            ]
                        ] : [],
                        $isShowResume ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Resume'),
                            'icon'  => ["fal", "fa-play"],
                            'route' => [
                                'name'       => "grp.models.shop.mailshot.resume",
                                'parameters' => [
                                    $this->shop->id,
                                    $mailshot->id
                                ],
                                'method'     => 'post'
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
                    'name' => 'grp.models.shop.outboxes.newsletter.send',
                    'parameters' => [
                        'shop' => $this->shop->id,
                        'outbox' => $mailshot->outbox->id,
                        'mailshot' => $mailshot->id
                    ],
                ],
                'scheduleMailshotRoute' => [
                    'name' => 'grp.models.shop.outboxes.newsletter.schedule',
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
                    'name' => 'grp.org.shops.show.crm.prospects.mailshots.index',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop' => $this->shop->slug
                    ],
                ],
                'cancelScheduleMailshotRoute' => [
                    'name' => 'grp.models.shop.outboxes.newsletter.cancel-schedule',
                    'parameters' => [
                        'shop' => $this->shop->id,
                        'outbox' => $mailshot->outbox->id,
                        'mailshot' => $mailshot->id
                    ],
                ],
                'setSecondWaveRoute' => [
                    'name' => 'grp.models.shop.mailshot.second-wave',
                    'parameters' => [
                        'shop' => $this->shop->id,
                        'mailshot' => $mailshot->id
                    ],
                ],
                'updateSecondWaveRoute' => [
                    'name' => 'grp.models.shop.mailshot.second-wave.update',
                    'parameters' => [
                        'shop' => $this->shop->id,
                        'mailshot' => $mailshot->id
                    ],
                ],
                'showLinkedMailShotRoute' => [
                    'name' => 'grp.org.shops.show.crm.prospects.mailshots.show',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop' => $this->shop->slug,
                        'mailshot' => $isSecondWaveActive ? $mailshotSecondWave?->slug : $mailshot->parentMailshot?->slug,
                    ],
                ],
                'status' => $mailshot->state->value,
                'secondWaveStatus' => $mailshot->secondWave?->state?->value,
                'estimatedRecipients' => $estimatedRecipients,
                'mailshotType' => $mailshot->type->value,
                'isSecondWaveActive' => $isSecondWaveActive,
                'secondwaveSubject' => $mailshotSecondWave?->subject,
                'secondwaveDelayHours' => $mailshotSecondWave?->send_delay_hours,
                'isHasParentMailshot' => $isHasParentMailshot,
                'isSecondWave' => $mailshot->is_second_wave,
                'numberSecondWaveRecipients' => $mailshotSecondWave?->recipients?->count() ?? 0,

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


    //  TODO: Fix the breadcrumbs
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
            'grp.org.shops.show.crm.prospects.mailshots.show' => array_merge(
                (new IndexProspects())->getBreadcrumbs(
                    'grp.org.shops.show.crm.prospects.index',
                    Arr::only($routeParameters, ['organisation', 'shop']),
                ),
                $headCrumb(
                    $mailshot,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.prospects.mailshots.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'prospect'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.prospects.mailshots.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'prospect', 'mailshot'])
                        ]
                    ],
                    $suffix
                )
            ),

            default => []
        };
    }
}

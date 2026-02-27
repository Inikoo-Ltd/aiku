<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 26 Feb 2026 16:38:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots\UI;

use App\Actions\Comms\Mailshot\GetMailshotMergeTags;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\CRM\Prospect;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\WithOutboxBuilder;
use App\Enums\UI\Mail\EmailTemplateTabsEnum;
use App\Actions\Comms\EmailTemplate\UI\IndexEmailTemplates;
use App\Http\Resources\Comms\MailshotTemplatesResource;
use App\Http\Resources\Mail\EmailTemplateResource;
use App\Actions\Comms\Mailshot\UI\IndexPreviousMailshotTemplates;
use App\Actions\Comms\Mailshot\UI\IndexMailshotFromOtherStoreTemplates;

class ShowProspectMailshotWorkshop extends OrgAction
{
    use WithActionButtons;
    use WithOutboxBuilder;

    public function handle(Mailshot $mailshot): Mailshot
    {
        $email = $mailshot->email;
        // generate email if not exist
        if (!$email) {
            $this->createMailShotEmail($mailshot->shop, OutboxCodeEnum::NEWSLETTER, $mailshot, $mailshot->outbox);
            $mailshot->refresh();
        }
        return $mailshot;
    }


    public function asController(Organisation $organisation, Shop $shop, Prospect $prospect, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request)->withTab(EmailTemplateTabsEnum::values());

        return $this->handle($mailshot);
    }

    public function htmlResponse(Mailshot $mailshot, ActionRequest $request): Response
    {
        $email = $mailshot->email;
        return Inertia::render(
            'Grp/Org/Web/Workshop/Mailshot/ProspectMailshotWorkshop',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $mailshot,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $mailshot->subject,
                'pageHead'    => [
                    'title'     => $mailshot->subject,
                    'icon'      => [
                        'tooltip' => __('snapshot'),
                        'icon'    => 'fal fa-mail-bulk'
                    ],
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit workshop'),
                            'route' => [
                                'name'       => preg_replace('/workshop$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ],
                    ]

                ],
                EmailTemplateTabsEnum::TEMPLATES->value => $this->tab == EmailTemplateTabsEnum::TEMPLATES->value ?
                    fn () => EmailTemplateResource::collection(
                        IndexEmailTemplates::run($mailshot->shop, EmailTemplateTabsEnum::TEMPLATES->value)
                    )
                    : Inertia::lazy(fn () => EmailTemplateResource::collection(
                        IndexEmailTemplates::run($mailshot->shop, EmailTemplateTabsEnum::TEMPLATES->value)
                    )),

                EmailTemplateTabsEnum::PREVIOUS_MAILSHOTS->value => $this->tab == EmailTemplateTabsEnum::PREVIOUS_MAILSHOTS->value
                    ?
                    fn () => MailshotTemplatesResource::collection(
                        IndexPreviousMailshotTemplates::run(
                            $mailshot->shop,
                            EmailTemplateTabsEnum::PREVIOUS_MAILSHOTS->value
                        )
                    )
                    : Inertia::lazy(fn () => MailshotTemplatesResource::collection(
                        IndexPreviousMailshotTemplates::run(
                            $mailshot->shop,
                            EmailTemplateTabsEnum::PREVIOUS_MAILSHOTS->value
                        )
                    )),
                EmailTemplateTabsEnum::OTHER_STORE_MAILSHOTS->value => $this->tab == EmailTemplateTabsEnum::OTHER_STORE_MAILSHOTS->value ?
                    fn () => MailshotTemplatesResource::collection(
                        IndexMailshotFromOtherStoreTemplates::run($mailshot->shop, EmailTemplateTabsEnum::OTHER_STORE_MAILSHOTS->value)
                    )
                    : Inertia::lazy(fn () => MailshotTemplatesResource::collection(
                        IndexMailshotFromOtherStoreTemplates::run($mailshot->shop, EmailTemplateTabsEnum::OTHER_STORE_MAILSHOTS->value)
                    )),



                'unpublished_layout' => $email->unpublishedSnapshot->layout,
                'snapshot'    => $email->unpublishedSnapshot,
                'builder'     => $email->builder,
                'imagesUploadRoute'   => [
                    'name'       => 'grp.models.email-templates.images.store',
                    'parameters' => $email->id
                ],
                'updateRoute'         => [
                    'name'       => 'grp.models.shop.mailshot.workshop.update',
                    'parameters' => [
                        'shop' => $mailshot->shop_id,
                        'mailshot' => $mailshot->id
                    ],
                    'method' => 'patch'
                ],
                'loadRoute'           => [
                    'name'       => 'grp.models.email-templates.content.show',
                    'parameters' => $email->id
                ],
                'publishRoute'           => [
                    'name'       => 'grp.models.shop.mailshot.publish',
                    'parameters' => [
                        'shop' => $mailshot->shop_id,
                        'mailshot' => $mailshot->id
                    ],
                    'method' => 'post'
                ],
                'storeNewTemplateRoute' => [
                    'name' => 'grp.models.shop.mailshot.store.as-new-template',
                    'parameters' => [
                        'shop' => $mailshot->shop_id,
                        'mailshot' => $mailshot->id
                    ],
                    'method' => 'post'
                ],
                'mergeTags' => GetMailshotMergeTags::run(),
                'status' => $email->outbox->state,
                'organisationSlug' => $this->organisation->slug,
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => EmailTemplateTabsEnum::navigation(),
                ],
            ]
        )->table(
            IndexEmailTemplates::make()->tableStructure(
                prefix: EmailTemplateTabsEnum::TEMPLATES->value
            )
        )->table(
            IndexPreviousMailshotTemplates::make()->tableStructure(
                prefix: EmailTemplateTabsEnum::PREVIOUS_MAILSHOTS->value
            )
        )->table(
            IndexMailshotFromOtherStoreTemplates::make()->tableStructure(
                prefix: EmailTemplateTabsEnum::OTHER_STORE_MAILSHOTS->value
            )
        );
    }

    public function getBreadcrumbs(Mailshot $mailshot, string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (Mailshot $mailshot, array $routeParameters) {
            return [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => "#",
                        'label' => __('Workshop')
                    ],
                ]
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.crm.prospects.mailshots.workshop' =>
            array_merge(
                ShowProspectMailshot::make()->getBreadcrumbs(
                    $mailshot,
                    'grp.org.shops.show.crm.prospects.mailshots.show',
                    $routeParameters,
                ),
                $headCrumb(
                    $mailshot,
                    $routeParameters
                ),
            ),
            default => []
        };
    }
}

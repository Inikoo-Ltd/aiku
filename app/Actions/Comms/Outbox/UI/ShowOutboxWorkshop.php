<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\UI;

use App\Actions\Comms\EmailTemplate\UI\IndexEmailTemplates;
use App\Actions\Comms\EmailTemplate\UI\IndexOtherStoreEmailTemplates;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Enums\Comms\Email\EmailBuilderEnum;
use App\Enums\UI\Mail\EmailTemplateTabsEnum;
use App\Http\Resources\Mail\EmailTemplateResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Email;
use App\Models\Comms\Outbox;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOutboxWorkshop extends OrgAction
{
    use WithActionButtons;


    private Fulfilment|Shop $parent;
    private Outbox $outbox;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Outbox $outbox): Email
    {

        if ($outbox->model_type === 'Mailshot') {
            abort(404);
        }
        if ($outbox->builder == EmailBuilderEnum::BLADE) {
            throw ValidationException::withMessages([
                'value' => 'Builder is not supported'
            ]);
        }

        return $outbox->emailOngoingRun->email;
    }

    /** @noinspection PhpUnusedParameterInspection */
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Organisation $organisation, Shop $shop, Outbox $outbox, ActionRequest $request): Email
    {
        $this->parent = $shop;
        $this->outbox = $outbox;
        $this->initialisationFromShop($shop, $request)->withTab($this->templateTabs());

        return $this->handle($outbox);
    }

    /** @noinspection PhpUnusedParameterInspection */
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function inWebsite(Organisation $organisation, Shop $shop, Website $website, Outbox $outbox, ActionRequest $request): Email
    {
        $this->parent = $shop;
        $this->outbox = $outbox;
        $this->initialisationFromShop($shop, $request)->withTab($this->templateTabs());

        return $this->handle($outbox);
    }


    /** @noinspection PhpUnusedParameterInspection */
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Outbox $outbox, ActionRequest $request): Email
    {
        $this->parent = $fulfilment;
        $this->outbox = $outbox;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab($this->templateTabs());

        return $this->handle($outbox);
    }

    private function templateTabs(): array
    {
        return [EmailTemplateTabsEnum::TEMPLATES->value, EmailTemplateTabsEnum::OTHER_STORE_TEMPLATES->value];
    }

    public function htmlResponse(Email $email, ActionRequest $request): Response
    {
        $shop = $email->shop;


        return Inertia::render(
            'Org/Web/Workshop/Outbox/OutboxWorkshop',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title' => $email->subject,
                'pageHead' => [
                    'title' => $email->subject,
                    'icon' => [
                        'tooltip' => __('mailshot'),
                        'icon' => 'fal fa-mail-bulk'
                    ],

                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'exit',
                            'label' => __('Exit workshop'),
                            'route' => [
                                'name' => preg_replace('/workshop$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ],

                    ]

                ],
                'unpublished_layout' => $email->unpublishedSnapshot->layout,
                'snapshot' => $email->unpublishedSnapshot,
                'builder' => $email->builder,
                'imagesUploadRoute' => [
                    'name' => 'grp.models.email-templates.images.store',
                    'parameters' => $email->id
                ],
                'updateRoute' => [
                    'name' => 'grp.models.shop.outboxes.workshop.update',
                    'parameters' => [
                        'shop' => $email->shop_id,
                        'outbox' => $email->outbox_id
                    ],
                    'method' => 'patch'
                ],
                'loadRoute' => [
                    'name' => 'grp.models.email-templates.content.show',
                    'parameters' => $email->id
                ],
                'publishRoute' => [
                    'name' => 'grp.models.shop.outboxes.publish',
                    'parameters' => [
                        'shop' => $email->shop_id,
                        'outbox' => $email->outbox_id
                    ],
                    'method' => 'post'
                ],
                'sendTestRoute' => $this->parent instanceof Fulfilment ? [
                    'name' => 'grp.models.fulfilment.outboxes.send.test',
                    'parameters' => [
                        'fulfilment' => $this->parent->id,
                        'outbox' => $email->outbox->id
                    ]
                ] : [
                    'name' => 'grp.models.shop.outboxes.send.test',
                    'parameters' => [
                        'shop' => $this->shop->id,
                        'outbox' => $email->outbox->id
                    ]
                ],
                'storeTemplateRoute' => [
                    'name' => 'grp.models.shop.outboxes.workshop.store.template',
                    'parameters' => [
                        'shop' => $email->shop_id,
                        'outbox' => $email->outbox_id
                    ],
                    'method' => 'post'
                ],
                'mergeTags' => GetOutboxMergeTagByOutbox::run($this->outbox),
                'status' => $email->outbox->state,
                'organisationSlug' => $this->organisation->slug,
                'shopSlug' => $email->shop?->slug,
                'shopId' => $email->shop_id,
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => EmailTemplateTabsEnum::navigationOnly($this->templateTabs()),
                ],
                EmailTemplateTabsEnum::TEMPLATES->value => $this->tab == EmailTemplateTabsEnum::TEMPLATES->value ?
                    fn () => EmailTemplateResource::collection(
                        IndexEmailTemplates::run($shop, EmailTemplateTabsEnum::TEMPLATES->value, true)
                    )
                    : Inertia::optional(fn () => EmailTemplateResource::collection(
                        IndexEmailTemplates::run($shop, EmailTemplateTabsEnum::TEMPLATES->value, true)
                    )),
                EmailTemplateTabsEnum::OTHER_STORE_TEMPLATES->value => $this->tab == EmailTemplateTabsEnum::OTHER_STORE_TEMPLATES->value ?
                    fn () => EmailTemplateResource::collection(
                        IndexOtherStoreEmailTemplates::run($shop, EmailTemplateTabsEnum::OTHER_STORE_TEMPLATES->value, true)
                    )
                    : Inertia::optional(fn () => EmailTemplateResource::collection(
                        IndexOtherStoreEmailTemplates::run($shop, EmailTemplateTabsEnum::OTHER_STORE_TEMPLATES->value, true)
                    )),
            ]
        )->table(
            IndexEmailTemplates::make()->tableStructure(
                prefix: EmailTemplateTabsEnum::TEMPLATES->value
            )
        )->table(
            IndexOtherStoreEmailTemplates::make()->tableStructure(
                prefix: EmailTemplateTabsEnum::OTHER_STORE_TEMPLATES->value
            )
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (Email $email, array $routeParameters, ?string $suffix = null) {
            return [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __($email->subject)
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        /** @var Outbox $outbox */
        $outbox = Outbox::firstWhere('slug', $routeParameters['outbox']);

        return match ($routeName) {
            'org.crm.shop.prospects.mailshots.workshop', 'grp.org.shops.show.dashboard.comms.outboxes.workshop' =>
            array_merge(
                ShowOutbox::make()->getBreadcrumbs(
                    'grp.org.shops.show.dashboard.comms.outboxes.workshop',
                    $routeParameters
                ),
                $headCrumb(
                    $outbox->emailOngoingRun->email,
                    [
                        'index' => [
                            'name' => 'grp.org.shops.show.dashboard.comms.outboxes.show',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name' => 'grp.org.shops.show.dashboard.comms.outboxes.workshop',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.fulfilments.show.operations.comms.outboxes.workshop' =>
            array_merge(
                ShowOutbox::make()->getBreadcrumbs(
                    'grp.org.fulfilments.show.operations.comms.outboxes.show',
                    $routeParameters
                ),
                $headCrumb(
                    $outbox->emailOngoingRun->email,
                    [
                        'index' => [
                            'name' => 'grp.org.fulfilments.show.operations.comms.outboxes.show',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name' => 'grp.org.fulfilments.show.operations.comms.outboxes.workshop',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),

            default => []
        };
    }
}

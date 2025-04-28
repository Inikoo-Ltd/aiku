<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 17:41:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer;

use App\Actions\Catalogue\HasRentalAgreement;
use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\UI\GetFulfilmentCustomerShowcase;
use App\Actions\Fulfilment\FulfilmentCustomer\UI\IndexCustomerBalanceTransaction;
use App\Actions\Fulfilment\RentalAgreementClause\UI\IndexRentalAgreementClauses;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItems;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Actions\Traits\WithWebUserMeta;
use App\Enums\Fulfilment\FulfilmentCustomer\FulfilmentCustomerStatusEnum;
use App\Enums\UI\Fulfilment\FulfilmentCustomerTabsEnum;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\Fulfilment\CustomerBalanceTransactionsResource;
use App\Http\Resources\Fulfilment\RentalAgreementClausesResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Mail\DispatchedEmailResource;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFulfilmentCustomer extends OrgAction
{
    use WithWebUserMeta;
    use HasRentalAgreement;
    use WithFulfilmentCustomerSubNavigation;
    use WithFulfilmentShopAuthorisation;

    public function handle(FulfilmentCustomer $fulfilmentCustomer): FulfilmentCustomer
    {
        return $fulfilmentCustomer;
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): FulfilmentCustomer
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(FulfilmentCustomerTabsEnum::values());

        return $this->handle($fulfilmentCustomer);
    }

    public function htmlResponse(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Response
    {
        $navigation = FulfilmentCustomerTabsEnum::navigation();

        if (!$fulfilmentCustomer->rentalAgreement || $fulfilmentCustomer->rentalAgreement?->clauses()->count() < 1) {
            unset($navigation[FulfilmentCustomerTabsEnum::AGREED_PRICES->value]);
        }

        $actions = [];
        if ($this->canEdit) {
            $additionalActions = [];

            if ($fulfilmentCustomer->status == FulfilmentCustomerStatusEnum::NO_RENTAL_AGREEMENT && !$fulfilmentCustomer->rentalAgreement()->exists()) {
                $additionalActions = [
                    [
                        'type'    => 'button',
                        'style'   => 'create',
                        'tooltip' => __('Create a Rental Agreement'),
                        'label'   => __('Rental Agreement'),
                        'route'   => [
                            'method'     => 'get',
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.rental-agreement.create',
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ],
                ];
            }

            if ($fulfilmentCustomer->rentalAgreement()->exists() && $fulfilmentCustomer->pallets_storage) {
                $additionalActions[] = [
                    'type'        => 'button',
                    'style'       => 'create',
                    'tooltip'     => __('Create a pallet Delivery'),
                    'fullLoading' => true,
                    'label'       => __('Delivery'),
                    'route'       => [
                        'method'     => 'post',
                        'name'       => 'grp.models.fulfilment-customer.pallet-delivery.store',
                        'parameters' => [
                            'fulfilmentCustomer' => $fulfilmentCustomer->id
                        ]
                    ]
                ];
            }

            if ($fulfilmentCustomer->number_pallets_status_storing > 0) {
                $additionalActions[] =
                    [
                        'type'        => 'button',
                        'style'       => 'create',
                        'tooltip'     => __('Create Return (Whole pallet)'),
                        'label'       => __('Return (Whole pallet)'),
                        'fullLoading' => true,
                        'route'       => [
                            'method'     => 'post',
                            'name'       => 'grp.models.fulfilment-customer.pallet-return.store',
                            'parameters' => [
                                'fulfilmentCustomer' => $fulfilmentCustomer->id
                            ]
                        ]
                    ];
            }
            if ($fulfilmentCustomer->number_pallets_status_storing > 0) {
                $additionalActions[] =
                    [
                        'type'        => 'button',
                        'style'       => 'create',
                        'tooltip'     => __('Create Return (Customer SKUs)'),
                        'label'       => __('Return (SKUs)'),
                        'fullLoading' => true,
                        'route'       => [
                            'method'     => 'post',
                            'name'       => 'grp.models.fulfilment-customer.pallet-return-stored-items.store',
                            'parameters' => [
                                'fulfilmentCustomer' => $fulfilmentCustomer->id
                            ]
                        ]
                    ];
            }

            $actions = [
                [
                    'type'    => 'button',
                    'style'   => 'edit',
                    'tooltip' => __('Edit Customer'),
                    'route'   => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.edit',
                        'parameters' => array_values($request->route()->originalParameters())
                    ]
                ],
                [
                    'type'   => 'buttonGroup',
                    'button' => $additionalActions
                ],
            ];
        }

        return Inertia::render(
            'Org/Fulfilment/FulfilmentCustomer',
            [
                'title'       => __('customer'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($fulfilmentCustomer, $request),
                    'next'     => $this->getNext($fulfilmentCustomer, $request),
                ],
                'pageHead'    => [
                    'icon'          => [
                        'title' => __('customer'),
                        'icon'  => 'fal fa-user',
                    ],
                    'iconRight'     => FulfilmentCustomerStatusEnum::statusIcon()[$fulfilmentCustomer->status->value],
                    'model'         => __('Customer'),
                    'subNavigation' => $this->getFulfilmentCustomerSubNavigation($fulfilmentCustomer, $request),
                    'title'         => $fulfilmentCustomer->customer->name,
                    'afterTitle'    => [
                        'label' => '('.$fulfilmentCustomer->customer->reference.')',
                    ],
                    'edit'          => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,
                    'actions'       => $actions
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],

                'attachmentRoutes'   => [
                    'attachRoute' => [
                        'name'       => 'grp.models.customer.attachment.attach',
                        'parameters' => [
                            'customer' => $fulfilmentCustomer->customer->id,
                        ],
                        'method'     => 'post'
                    ],
                    'detachRoute' => [
                        'name'       => 'grp.models.customer.attachment.detach',
                        'parameters' => [
                            'customer' => $fulfilmentCustomer->customer->id,
                        ],
                        'method'     => 'delete'
                    ]
                ],
                'option_attach_file' => [
                    [
                        'name' => __('Other'),
                        'code' => 'Other'
                    ]
                ],


                FulfilmentCustomerTabsEnum::SHOWCASE->value => $this->tab == FulfilmentCustomerTabsEnum::SHOWCASE->value ?
                    fn () => GetFulfilmentCustomerShowcase::run($fulfilmentCustomer, $request)
                    : Inertia::lazy(fn () => GetFulfilmentCustomerShowcase::run($fulfilmentCustomer, $request)),

                FulfilmentCustomerTabsEnum::AGREED_PRICES->value => $this->tab == FulfilmentCustomerTabsEnum::AGREED_PRICES->value ?
                    fn () => RentalAgreementClausesResource::collection(IndexRentalAgreementClauses::run($fulfilmentCustomer, FulfilmentCustomerTabsEnum::AGREED_PRICES->value))
                    : Inertia::lazy(fn () => RentalAgreementClausesResource::collection(IndexRentalAgreementClauses::run($fulfilmentCustomer, FulfilmentCustomerTabsEnum::AGREED_PRICES->value))),


                FulfilmentCustomerTabsEnum::BALANCE->value => $this->tab == FulfilmentCustomerTabsEnum::BALANCE->value ?
                    fn () => CustomerBalanceTransactionsResource::collection(IndexCustomerBalanceTransaction::run($fulfilmentCustomer->customer, FulfilmentCustomerTabsEnum::BALANCE->value))
                    : Inertia::lazy(fn () => CustomerBalanceTransactionsResource::collection(IndexCustomerBalanceTransaction::run($fulfilmentCustomer->customer, FulfilmentCustomerTabsEnum::BALANCE->value))),

                FulfilmentCustomerTabsEnum::EMAIL->value => $this->tab == FulfilmentCustomerTabsEnum::EMAIL->value ?
                    fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($fulfilmentCustomer->customer, FulfilmentCustomerTabsEnum::EMAIL->value))
                    : Inertia::lazy(fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($fulfilmentCustomer->customer, FulfilmentCustomerTabsEnum::EMAIL->value))),


                FulfilmentCustomerTabsEnum::HISTORY->value => $this->tab == FulfilmentCustomerTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($fulfilmentCustomer->customer))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($fulfilmentCustomer->customer))),

                FulfilmentCustomerTabsEnum::ATTACHMENTS->value => $this->tab == FulfilmentCustomerTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($fulfilmentCustomer->customer, FulfilmentCustomerTabsEnum::ATTACHMENTS->value))
                    : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run($fulfilmentCustomer->customer, FulfilmentCustomerTabsEnum::ATTACHMENTS->value))),
            ]
        )
            ->table(IndexStoredItems::make()->tableStructure($fulfilmentCustomer->storedItems))
            ->table(IndexRentalAgreementClauses::make()->tableStructure(prefix: FulfilmentCustomerTabsEnum::AGREED_PRICES->value))
            ->table(IndexDispatchedEmails::make()->tableStructure($fulfilmentCustomer, prefix: FulfilmentCustomerTabsEnum::EMAIL->value))
            ->table(IndexHistory::make()->tableStructure(prefix: FulfilmentCustomerTabsEnum::HISTORY->value))
            ->table(IndexCustomerBalanceTransaction::make()->tableStructure($fulfilmentCustomer->customer, prefix: FulfilmentCustomerTabsEnum::BALANCE->value))
            ->table(IndexAttachments::make()->tableStructure(FulfilmentCustomerTabsEnum::ATTACHMENTS->value));
    }


    public function jsonResponse(Customer $fulfilmentCustomer): CustomersResource
    {
        return new CustomersResource($fulfilmentCustomer);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $headCrumb = function (FulfilmentCustomer $fulfilmentCustomer, array $routeParameters, string $suffix = '') {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Customers')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $fulfilmentCustomer->customer->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        if (Arr::get($routeParameters, 'pallet')) {
            $pallet             = Pallet::where('slug', $routeParameters['pallet'])->withTrashed()->first();
            $fulfilmentCustomer = $pallet->fulfilmentCustomer->slug;
        } else {
            $fulfilmentCustomer = $routeParameters['fulfilmentCustomer'];
        }

        $fulfilmentCustomer = FulfilmentCustomer::where('slug', $fulfilmentCustomer)->first();

        return array_merge(
            ShowFulfilment::make()->getBreadcrumbs(
                Arr::only($routeParameters, ['organisation', 'fulfilment'])
            ),
            $headCrumb(
                $fulfilmentCustomer,
                [

                    'index' => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                    ],
                    'model' => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show',
                        'parameters' => Arr::only(
                            $routeParameters,
                            ['organisation', 'fulfilment', 'fulfilmentCustomer']
                        )
                    ]
                ]
            )
        );
    }

    public function getPrevious(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): ?array
    {
        $previous = FulfilmentCustomer::where('slug', '<', $fulfilmentCustomer->slug)
            ->where('fulfilment_customers.fulfilment_id', $fulfilmentCustomer->fulfilment_id)
            ->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): ?array
    {
        $next = FulfilmentCustomer::where('slug', '>', $fulfilmentCustomer->slug)
            ->where('fulfilment_customers.fulfilment_id', $fulfilmentCustomer->fulfilment_id)
            ->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?FulfilmentCustomer $fulfilmentCustomer, string $routeName): ?array
    {
        if (!$fulfilmentCustomer) {
            return null;
        }

        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show' => [
                'label' => $fulfilmentCustomer->customer->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'       => $fulfilmentCustomer->organisation->slug,
                        'fulfilment'         => $this->fulfilment->slug,
                        'fulfilmentCustomer' => $fulfilmentCustomer->slug
                    ]

                ]
            ],
        };
    }
}

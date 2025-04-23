<?php

/*
 * author Arya Permana - Kirin
 * created on 08-04-2025-16h-34m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletDelivery\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInDelivery;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\UI\Fulfilment\PalletDeliveryTabsEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Http\Resources\Fulfilment\FulfilmentTransactionsResource;
use App\Http\Resources\Fulfilment\PalletDeliveryResource;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPalletDeliveryDeleted extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithFulfilmentCustomerSubNavigation;

    private Warehouse|FulfilmentCustomer|Fulfilment $parent;

    public function handle(PalletDelivery $palletDelivery): PalletDelivery
    {
        return $palletDelivery;
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, $palletDeliverySlug, ActionRequest $request): PalletDelivery
    {
        $palletDelivery = PalletDelivery::onlyTrashed()->where('slug', $palletDeliverySlug)->first();
        if (!$palletDelivery) {
            abort(404);
        }
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletDeliveryTabsEnum::values());

        return $this->handle($palletDelivery);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouse(
        Organisation $organisation,
        Warehouse $warehouse,
        $palletDeliverySlug,
        ActionRequest $request
    ): PalletDelivery {
        $palletDelivery = PalletDelivery::onlyTrashed()->where('slug', $palletDeliverySlug)->first();
        if (!$palletDelivery) {
            abort(404);
        }
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletDeliveryTabsEnum::values());

        return $this->handle($palletDelivery);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(
        Organisation $organisation,
        Fulfilment $fulfilment,
        FulfilmentCustomer $fulfilmentCustomer,
        $palletDeliverySlug,
        ActionRequest $request
    ): PalletDelivery {
        $palletDelivery = PalletDelivery::onlyTrashed()->where('slug', $palletDeliverySlug)->first();
        if (!$palletDelivery) {
            abort(404);
        }
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletDeliveryTabsEnum::values());


        return $this->handle($palletDelivery);
    }

    public function htmlResponse(PalletDelivery $palletDelivery, ActionRequest $request): Response
    {
        $subNavigation = [];
        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        }


        $showGrossAndDiscount = $palletDelivery->gross_amount !== $palletDelivery->net_amount;

        $recurringBillData = null;
        if ($palletDelivery->recurringBill) {
            $recurringBill      = $palletDelivery->recurringBill;
            $recurringBillRoute = null;
            if ($this->parent instanceof Fulfilment) {
                $recurringBillRoute = [
                    'name'       => 'grp.org.fulfilments.show.operations.recurring_bills.current.show',
                    'parameters' => [
                        'organisation'  => $recurringBill->organisation->slug,
                        'fulfilment'    => $this->parent->slug,
                        'recurringBill' => $recurringBill->slug
                    ]
                ];
            } elseif ($this->parent instanceof FulfilmentCustomer) {
                $recurringBillRoute = [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.recurring_bills.show',
                    'parameters' => [
                        'organisation'       => $recurringBill->organisation->slug,
                        'fulfilment'         => $this->parent->fulfilment->slug,
                        'fulfilmentCustomer' => $this->parent->slug,
                        'recurringBill'      => $recurringBill->slug
                    ]
                ];
            }
            if ($recurringBillRoute) {
                $recurringBillData = [
                    'reference'    => $recurringBill->reference,
                    'status'       => $recurringBill->status,
                    'total_amount' => $recurringBill->total_amount,
                    'route'        => $recurringBillRoute
                ];
            }
        }

        return Inertia::render(
            'Org/Fulfilment/PalletDelivery',
            [
                'title'       => __('pallet delivery'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    // 'container' => $container,
                    'title'         => $palletDelivery->reference,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-truck-couch'],
                        'title' => $palletDelivery->reference
                    ],
                    'subNavigation' => $subNavigation,
                    'model'         => __('pallet delivery'),
                    'iconRight'     => $palletDelivery->state->stateIcon()[$palletDelivery->state->value],
                    // 'actions'       => $actions,
                ],

                'can_edit_transactions' => true,

                'interest' => [
                    'pallets_storage' => $palletDelivery->fulfilmentCustomer->pallets_storage,
                    'items_storage'   => $palletDelivery->fulfilmentCustomer->items_storage,
                    'dropshipping'    => $palletDelivery->fulfilmentCustomer->dropshipping,
                ],

                'help_articles' => [
                    [
                        'label'       => __('How to add a pallet'),
                        'type'        => 'video',
                        'description' => __('Learn how to add a pallet to a pallet delivery'),
                        'url'         => 'https://drive.google.com/file/d/1egAxAHT6eTDy3xz2xWfnto4-TbL4oIht/view'
                    ]
                ],
                'tabs'          => [
                    'current'    => $this->tab,
                    'navigation' => PalletDeliveryTabsEnum::navigation($palletDelivery)
                ],

                'data'       => PalletDeliveryResource::make($palletDelivery),
                'box_stats'  => [
                    'fulfilment_customer' => FulfilmentCustomerResource::make($palletDelivery->fulfilmentCustomer)->getArray(),
                    'delivery_state'      => PalletDeliveryStateEnum::stateIcon()[$palletDelivery->state->value],
                    'recurring_bill'      => $recurringBillData,
                    'order_summary'       => [


                        $showGrossAndDiscount ? [
                            [
                                'label'       => __('Gross'),
                                'information' => '',
                                'price_total' => $palletDelivery->gross_amount
                            ],
                            [
                                'label'       => __('Discounts'),
                                'information' => '',
                                'price_total' => $palletDelivery->discount_amount
                            ],
                        ] : [],
                        [
                            [
                                'label'       => __('Net'),
                                'information' => '',
                                'price_total' => $palletDelivery->net_amount
                            ],
                            [
                                'label'       => __('Tax').' '.$palletDelivery->taxCategory->rate * 100 .'%',
                                'information' => '',
                                'price_total' => $palletDelivery->tax_amount
                            ],
                        ],
                        [
                            [
                                'label'       => __('Total'),
                                'price_total' => $palletDelivery->total_amount
                            ],
                        ],

                        'currency' => CurrencyResource::make($palletDelivery->currency),
                    ]
                ],
                'notes_data' => [
                    [
                        'label'    => __('Customer'),
                        'note'     => $palletDelivery->customer_notes ?? '',
                        'editable' => false,
                        'bgColor'  => 'blue',
                        'field'    => 'customer_notes'
                    ],
                    [
                        'label'    => __('Public'),
                        'note'     => $palletDelivery->public_notes ?? '',
                        'editable' => true,
                        'bgColor'  => 'pink',
                        'field'    => 'public_notes'
                    ],
                    [
                        'label'    => __('Private'),
                        'note'     => $palletDelivery->internal_notes ?? '',
                        'editable' => true,
                        'bgColor'  => 'purple',
                        'field'    => 'internal_notes'
                    ],
                ],

                'option_attach_file' => [
                    [
                        'name' => __('Other'),
                        'code' => 'Other'
                    ]
                ],

                PalletDeliveryTabsEnum::PALLETS->value => $this->tab == PalletDeliveryTabsEnum::PALLETS->value ?
                    fn () => PalletsResource::collection(IndexPalletsInDelivery::run($palletDelivery, PalletDeliveryTabsEnum::PALLETS->value))
                    : Inertia::lazy(fn () => PalletsResource::collection(IndexPalletsInDelivery::run($palletDelivery, PalletDeliveryTabsEnum::PALLETS->value))),

                PalletDeliveryTabsEnum::SERVICES->value => $this->tab == PalletDeliveryTabsEnum::SERVICES->value ?
                    fn () => FulfilmentTransactionsResource::collection(IndexServiceInPalletDelivery::run($palletDelivery, PalletDeliveryTabsEnum::SERVICES->value))
                    : Inertia::lazy(fn () => FulfilmentTransactionsResource::collection(IndexServiceInPalletDelivery::run($palletDelivery, PalletDeliveryTabsEnum::SERVICES->value))),

                PalletDeliveryTabsEnum::PHYSICAL_GOODS->value => $this->tab == PalletDeliveryTabsEnum::PHYSICAL_GOODS->value ?
                    fn () => FulfilmentTransactionsResource::collection(IndexPhysicalGoodInPalletDelivery::run($palletDelivery, PalletDeliveryTabsEnum::PHYSICAL_GOODS->value))
                    : Inertia::lazy(fn () => FulfilmentTransactionsResource::collection(IndexPhysicalGoodInPalletDelivery::run($palletDelivery, PalletDeliveryTabsEnum::PHYSICAL_GOODS->value))),

                PalletDeliveryTabsEnum::ATTACHMENTS->value => $this->tab == PalletDeliveryTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($palletDelivery, PalletDeliveryTabsEnum::ATTACHMENTS->value))
                    : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run($palletDelivery, PalletDeliveryTabsEnum::ATTACHMENTS->value))),

            ]
        )->table(
            IndexPalletsInDelivery::make()->tableStructure(
                $palletDelivery,
                prefix: PalletDeliveryTabsEnum::PALLETS->value
            )
        )->table(
            IndexServiceInPalletDelivery::make()->tableStructure(
                $palletDelivery,
                prefix: PalletDeliveryTabsEnum::SERVICES->value
            )
        )->table(
            IndexPhysicalGoodInPalletDelivery::make()->tableStructure(
                $palletDelivery,
                prefix: PalletDeliveryTabsEnum::PHYSICAL_GOODS->value
            )
        )->table(IndexAttachments::make()->tableStructure(PalletDeliveryTabsEnum::ATTACHMENTS->value));
    }

    public function jsonResponse(PalletDelivery $palletDelivery): PalletDeliveryResource
    {
        return new PalletDeliveryResource($palletDelivery);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (PalletDelivery $palletDelivery, array $routeParameters) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Pallet deliveries')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $palletDelivery->slug,
                        ],

                    ],
                    'suffix'         => __('(🗑️ Deleted)'),
                ],
            ];
        };

        $palletDelivery = PalletDelivery::where('slug', $routeParameters['palletDelivery'])->withTrashed()->first();


        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show.deleted_pallet_deliveries.show' =>
            array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs(
                    Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                ),
                $headCrumb(
                    $palletDelivery,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.index',
                            'parameters' => Arr::only(
                                $routeParameters,
                                ['organisation', 'fulfilment', 'fulfilmentCustomer']
                            )
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.deleted_pallet_deliveries.show',
                            'parameters' => Arr::only(
                                $routeParameters,
                                ['organisation', 'fulfilment', 'fulfilmentCustomer', 'palletDelivery']
                            )
                        ]
                    ]
                )
            ),
            default => []
        };
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Apr 2024 13:42:37 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\WithInvoicePayBox;
use App\Actions\Accounting\InvoiceTransaction\UI\IndexInvoiceTransactionsGroupedByAsset;
use App\Actions\Accounting\InvoiceTransaction\UI\IndexInvoiceTransactions;
use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\UI\Accounting\FulfilmentInvoiceTabsEnum;
use App\Http\Resources\Accounting\FulfilmentInvoiceTransactionsResource;
use App\Http\Resources\Accounting\InvoiceResource;
use App\Http\Resources\Accounting\ItemizedInvoiceTransactionsResource;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\Accounting\RefundResource;
use App\Http\Resources\Accounting\RefundsResource;
use App\Http\Resources\Mail\DispatchedEmailResource;
use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFulfilmentInvoice extends OrgAction
{
    use IsInvoiceUI;
    use WithInvoicePayBox;
    use WithFulfilmentCustomerSubNavigation;
    use WithFulfilmentShopAuthorisation;

    private Organisation|Fulfilment|FulfilmentCustomer $parent;

    public function handle(Invoice $invoice): Invoice
    {
        return $invoice;
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(FulfilmentInvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(FulfilmentInvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    public function getRecurringBillRoute(Invoice $invoice): ?array
    {
        if ($invoice->shop->type !== ShopTypeEnum::FULFILMENT) {
            return null;
        }
        $recurringBillRoute = null;
        if ($invoice->recurringBill()->exists()) {
            if ($this->parent instanceof Fulfilment) {
                $recurringBillRoute = [
                    'name'       => 'grp.org.fulfilments.show.operations.recurring_bills.show',
                    'parameters' => [$invoice->organisation->slug, $this->parent->slug, $invoice->recurringBill->slug]
                ];
            } elseif ($this->parent instanceof FulfilmentCustomer) {
                $recurringBillRoute = [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.recurring_bills.show',
                    'parameters' => [$invoice->organisation->slug, $this->parent->fulfilment->slug, $this->parent->slug, $invoice->recurringBill->slug]
                ];
            }
        }

        return $recurringBillRoute;
    }

    public function htmlResponse(Invoice $invoice, ActionRequest $request): Response
    {
        $subNavigation = [];
        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        }

        $payBoxData           = $this->getPayBoxData($invoice);
        $actions              = $this->getInvoiceActions($invoice, $request, $payBoxData);
        $exportInvoiceOptions = ShowInvoice::make()->getExportOptions($invoice);

        $boxStats = $this->getBoxStats($invoice);

        data_set(
            $boxStats,
            'information.recurring_bill',
            [
                'reference' => $invoice->reference,
                'route'     => $this->getRecurringBillRoute($invoice)
            ]
        );

        return Inertia::render(
            'Org/Accounting/Invoice',
            [
                'title'       => __('invoice'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $invoice,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($invoice, $request),
                    'next'     => $this->getNext($invoice, $request),
                ],
                'pageHead'    => [
                    'subNavigation' => $subNavigation,
                    'model'         => __('invoice'),
                    'title'         => $invoice->reference,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-file-invoice-dollar'],
                        'title' => $invoice->reference
                    ],
                    'actions'       => $actions
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => FulfilmentInvoiceTabsEnum::navigation()
                ],

                'order_summary'        => ShowInvoice::make()->getInvoiceSummary($invoice),
                ...$payBoxData,
                'invoiceExportOptions' => $exportInvoiceOptions,
                'box_stats'            => $boxStats,
                'list_refunds'         => RefundResource::collection($invoice->refunds),
                'invoice'              => InvoiceResource::make($invoice),
                'outbox'               => [
                    'state'          => $invoice->shop->outboxes()->where('code', OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER->value)->first()?->state->value,
                    'workshop_route' => $this->getOutboxRoute($invoice)
                ],

                FulfilmentInvoiceTabsEnum::REFUNDS->value => $this->tab == FulfilmentInvoiceTabsEnum::REFUNDS->value
                    ? fn () => RefundsResource::collection(IndexRefunds::run($invoice, FulfilmentInvoiceTabsEnum::REFUNDS->value))
                    : Inertia::lazy(fn () => RefundsResource::collection(IndexRefunds::run($invoice, FulfilmentInvoiceTabsEnum::REFUNDS->value))),

                FulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS->value => $this->tab == FulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS->value ?
                    fn () => FulfilmentInvoiceTransactionsResource::collection(IndexInvoiceTransactionsGroupedByAsset::run($invoice, FulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS->value))
                    : Inertia::lazy(fn () => FulfilmentInvoiceTransactionsResource::collection(IndexInvoiceTransactionsGroupedByAsset::run($invoice, FulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS->value))),

                FulfilmentInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS->value => $this->tab == FulfilmentInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS->value ?
                    fn () => ItemizedInvoiceTransactionsResource::collection(IndexInvoiceTransactions::run($invoice, FulfilmentInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS->value))
                    : Inertia::lazy(fn () => ItemizedInvoiceTransactionsResource::collection(IndexInvoiceTransactions::run($invoice, FulfilmentInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS->value))),

                FulfilmentInvoiceTabsEnum::EMAIL->value => $this->tab == FulfilmentInvoiceTabsEnum::EMAIL->value ?
                    fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($invoice->customer, FulfilmentInvoiceTabsEnum::EMAIL->value))
                    : Inertia::lazy(fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($invoice->customer, FulfilmentInvoiceTabsEnum::EMAIL->value))),


                FulfilmentInvoiceTabsEnum::PAYMENTS->value => $this->tab == FulfilmentInvoiceTabsEnum::PAYMENTS->value ?
                    fn () => PaymentsResource::collection(IndexPayments::run($invoice))
                    : Inertia::lazy(fn () => PaymentsResource::collection(IndexPayments::run($invoice))),


            ]
        )->table(IndexPayments::make()->tableStructure($invoice, [], FulfilmentInvoiceTabsEnum::PAYMENTS->value))
            ->table(IndexRefunds::make()->tableStructure(parent: $invoice, prefix: FulfilmentInvoiceTabsEnum::REFUNDS->value))
            ->table(IndexDispatchedEmails::make()->tableStructure($invoice->customer, prefix: FulfilmentInvoiceTabsEnum::EMAIL->value))
            ->table(IndexInvoiceTransactionsGroupedByAsset::make()->tableStructure($invoice, FulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS->value))
            ->table(IndexInvoiceTransactions::make()->tableStructure(FulfilmentInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS->value));
    }


    public function jsonResponse(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice);
    }


    public function getBreadcrumbs(Invoice $invoice, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Invoice $invoice, array $routeParameters, string $suffix = null, $suffixIndex = '') {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Invoices').$suffixIndex,
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $invoice->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.fulfilments.show.operations.invoices.show',
            => array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.all.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'invoice'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.fulfilments.show.operations.invoices.all_invoices.show',
            => array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.all.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.all_invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'invoice'])
                        ]
                    ],
                    $suffix,
                    ' ('.__('All').')'
                ),
            ),
            'grp.org.fulfilments.show.operations.invoices.unpaid_invoices.show',
            => array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.operations.unpaid_invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.unpaid_invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'invoice'])
                        ]
                    ],
                    $suffix,
                    ' ('.__('Unpaid').')'
                ),
            ),

            'grp.org.fulfilments.show.crm.customers.show.invoices.show',
            => array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer', 'invoice'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }


    protected function getNavigation(?Invoice $invoice, string $routeName): ?array
    {
        if (!$invoice) {
            return null;
        }

        return match ($routeName) {
            'grp.org.fulfilments.show.operations.invoices.all_invoices.show',
            'grp.org.fulfilments.show.operations.invoices.unpaid_invoices.show',
            'grp.org.fulfilments.show.operations.invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'fulfilment'   => $this->parent->slug,
                        'invoice'      => $invoice->slug
                    ]

                ]
            ],
            'grp.org.fulfilments.show.crm.customers.show.invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'       => $invoice->organisation->slug,
                        'fulfilment'         => $invoice->shop->fulfilment->slug,
                        'fulfilmentCustomer' => $this->parent->slug,
                        'invoice'            => $invoice->slug
                    ]
                ]
            ],
        };
    }
}

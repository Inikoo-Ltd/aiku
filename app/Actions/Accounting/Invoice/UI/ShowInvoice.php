<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Apr 2024 13:42:37 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\WithInvoicePayBox;
use App\Actions\Accounting\InvoiceTransaction\UI\IndexInvoiceTransactions;
use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\UI\Accounting\ShowAccountingDashboard;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\UI\Accounting\InvoiceTabsEnum;
use App\Http\Resources\Accounting\InvoiceResource;
use App\Http\Resources\Accounting\InvoiceTransactionsResource;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\Accounting\RefundResource;
use App\Http\Resources\Accounting\RefundsResource;
use App\Http\Resources\Mail\DispatchedEmailResource;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Dropshipping\CustomerClient;
use App\Models\SysAdmin\Organisation;
use Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowInvoice extends OrgAction
{
    use IsInvoiceUI;
    use WithInvoicePayBox;
    use WithFulfilmentCustomerSubNavigation;

    private Organisation|Shop|CustomerClient $parent;

    public function handle(Invoice $invoice): Invoice
    {
        return $invoice;
    }


    public function asController(Organisation $organisation, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(InvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(InvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    public function inCustomerClient(Organisation $organisation, Shop $shop, Customer $customer, CustomerHasPlatform $customerHasPlatform, CustomerClient $customerClient, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->parent = $customerClient;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($invoice);
    }

    /**
     * Get structured invoice summary for display in the UI.
     *
     * NOTE: Used in deleted invoices as well ShowInvoiceDeleted.php
     *
     * Returns a multidimensional array with three sections:
     * 1. Product/service line items (conditionally showing services and rentals)
     * 2. Additional costs (charges, shipping, insurance, tax)
     * 3. Total amount
     *
     * @param  Invoice  $invoice  The invoice model to generate summary for
     *
     * @return array Structured array of invoice summary data for UI rendering
     */
    public function getInvoiceSummary(Invoice $invoice): array
    {
        return [
            array_values(
                array_filter(
                    [
                        $invoice->shop->fulfilment || $invoice->services_amount > 0 ? [
                            'label'       => __('Services'),
                            'price_total' => $invoice->services_amount
                        ] : null,

                        [
                            'label'       => __('Physical Goods'),
                            'price_total' => $invoice->goods_amount
                        ],

                        $invoice->shop->fulfilment || $invoice->rental_amount > 0 ? [
                            'label'       => __('Rentals'),
                            'price_total' => $invoice->rental_amount
                        ] : null
                    ]
                )
            ),
            [
                [
                    'label'       => __('Charges'),
                    // 'information'   => __('Shipping fee to your address using DHL service.'),
                    'price_total' => $invoice->charges_amount
                ],
                [
                    'label'       => __('Shipping'),
                    // 'information'   => __('Tax is based on 10% of total order.'),
                    'price_total' => $invoice->shipping_amount
                ],
                [
                    'label'       => __('Insurance'),
                    // 'information'   => __('Tax is based on 10% of total order.'),
                    'price_total' => $invoice->insurance_amount
                ],
                [
                    'label'       => __('Tax'),
                    'price_total' => $invoice->tax_amount
                ]
            ],
            [
                [
                    'label'       => __('Total'),
                    'price_total' => $invoice->total_amount
                ],
            ],
        ];
    }

    public function getExportOptions(Invoice $invoice): array
    {
        return [
            [
                'type'       => 'pdf',
                'icon'       => 'fas fa-file-pdf',
                'label'      => 'PDF',
                'tooltip'    => __('Download PDF'),
                'name'       => 'grp.org.accounting.invoices.download',
                'parameters' => [
                    'organisation' => $invoice->organisation->slug,
                    'invoice'      => $invoice->slug
                ]
            ],
            [
                'type'       => 'isDoc',
                'icon'       => 'fas fa-hockey-puck',
                'tooltip'    => __('Download Doc'),
                'label'      => 'IsDoc',
                'name'       => 'grp.org.accounting.invoices.show.is_doc',
                'parameters' => [
                    'organisation' => $invoice->organisation->slug,
                    'invoice'      => $invoice->slug
                ]
            ]

        ];
    }

    public function htmlResponse(Invoice $invoice, ActionRequest $request): Response
    {
        $subNavigation = [];

        $payBoxData           = $this->getPayBoxData($invoice);
        $actions              = $this->getInvoiceActions($invoice, $request, $payBoxData);
        $exportInvoiceOptions = $this->getExportOptions($invoice);


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
                    'subNavigation'   => $subNavigation,
                    'model'           => __('invoice'),
                    'title'           => $invoice->reference,
                    'icon'            => [
                        'icon'  => ['fal', 'fa-file-invoice-dollar'],
                        'title' => $invoice->reference
                    ],
                    'wrapped_actions' => $actions
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => InvoiceTabsEnum::navigation()
                ],

                'order_summary' => $this->getInvoiceSummary($invoice),

                ...$payBoxData,

                'invoiceExportOptions' => $exportInvoiceOptions,


                'box_stats'    => $this->getBoxStats($invoice),
                'list_refunds' => RefundResource::collection($invoice->refunds),
                'invoice'      => InvoiceResource::make($invoice),
                'outbox'       => [
                    'state'          => $invoice->shop->outboxes()->where('code', OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER->value)->first()?->state->value,
                    'workshop_route' => $this->getOutboxRoute($invoice)
                ],

                InvoiceTabsEnum::REFUNDS->value => $this->tab == InvoiceTabsEnum::REFUNDS->value
                    ? fn () => RefundsResource::collection(IndexRefunds::run($invoice, InvoiceTabsEnum::REFUNDS->value))
                    : Inertia::lazy(fn () => RefundsResource::collection(IndexRefunds::run($invoice, InvoiceTabsEnum::REFUNDS->value))),

                InvoiceTabsEnum::INVOICE_TRANSACTIONS->value => $this->tab == InvoiceTabsEnum::INVOICE_TRANSACTIONS->value ?
                    fn () => InvoiceTransactionsResource::collection(IndexInvoiceTransactions::run($invoice, InvoiceTabsEnum::INVOICE_TRANSACTIONS->value))
                    : Inertia::lazy(fn () => InvoiceTransactionsResource::collection(IndexInvoiceTransactions::run($invoice, InvoiceTabsEnum::INVOICE_TRANSACTIONS->value))),


                InvoiceTabsEnum::EMAIL->value => $this->tab == InvoiceTabsEnum::EMAIL->value ?
                    fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($invoice->customer, InvoiceTabsEnum::EMAIL->value))
                    : Inertia::lazy(fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($invoice->customer, InvoiceTabsEnum::EMAIL->value))),


                InvoiceTabsEnum::PAYMENTS->value => $this->tab == InvoiceTabsEnum::PAYMENTS->value ?
                    fn () => PaymentsResource::collection(IndexPayments::run($invoice))
                    : Inertia::lazy(fn () => PaymentsResource::collection(IndexPayments::run($invoice))),


            ]
        )->table(IndexPayments::make()->tableStructure($invoice, [], InvoiceTabsEnum::PAYMENTS->value))
            ->table(IndexRefunds::make()->tableStructure(parent: $invoice, prefix: InvoiceTabsEnum::REFUNDS->value))
            ->table(IndexDispatchedEmails::make()->tableStructure($invoice->customer, prefix: InvoiceTabsEnum::EMAIL->value))
            ->table(IndexInvoiceTransactions::make()->tableStructure(InvoiceTabsEnum::INVOICE_TRANSACTIONS->value));
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
            'grp.org.accounting.invoices.all_invoices.show',
            => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.invoices.all_invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'invoice'])
                        ]
                    ],
                    $suffix,
                    ' ('.__('All').')'
                ),
            ),

            'grp.org.accounting.invoices.unpaid_invoices.show',
            => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.invoices.unpaid_invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.invoices.unpaid_invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'invoice'])
                        ]
                    ],
                    $suffix,
                    ' ('.__('Unpaid').')'
                ),
            ),

            'grp.org.accounting.invoices.show',
            => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'invoice'])
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
            'grp.org.accounting.invoices.show', 'grp.org.accounting.invoices.all_invoices.show', 'grp.org.accounting.invoices.unpaid_invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'invoice'      => $invoice->slug
                    ]

                ]
            ],
            'grp.org.shops.show.dashboard.invoices.invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'shop'         => $this->parent->slug,
                        'invoice'      => $invoice->slug
                    ]

                ]
            ],
        };
    }
}

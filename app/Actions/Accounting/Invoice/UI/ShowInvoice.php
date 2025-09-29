<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Apr 2024 13:42:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\WithInvoicePayBox;
use App\Actions\Accounting\InvoiceTransaction\UI\IndexInvoiceTransactions;
use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\UI\Accounting\FulfilmentInvoiceTabsEnum;
use App\Enums\UI\Accounting\InvoiceTabsEnum;
use App\Http\Resources\Accounting\InvoiceResource;
use App\Http\Resources\Accounting\InvoiceTransactionsResource;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\Accounting\RefundResource;
use App\Http\Resources\Accounting\RefundsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Mail\DispatchedEmailsResource;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowInvoice extends OrgAction
{
    use IsInvoiceUI;
    use WithInvoicePayBox;
    use WithFulfilmentCustomerSubNavigation;

    private Organisation|Shop $parent;

    public function handle(Invoice $invoice): Invoice
    {
        return $invoice;
    }


    public function asController(Organisation $organisation, Invoice $invoice, ActionRequest $request)
    {
        $this->parent = $organisation;

        if ($invoice->shop->type == ShopTypeEnum::FULFILMENT) {
            $tabs = FulfilmentInvoiceTabsEnum::values();
        } else {
            $tabs = InvoiceTabsEnum::values();
        }

        $this->initialisationFromShop($invoice->shop, $request)->withTab($tabs);

        return $this->handle($invoice);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(InvoiceTabsEnum::values());

        return $this->handle($invoice);
    }


    /**
     * Get a structured invoice summary for display in the UI.
     *
     * NOTE: Used in deleted invoices as well as ShowDeletedInvoice.php
     *
     * Returns a multidimensional array with three sections:
     * 1. Product/service line items (conditionally showing services and rentals)
     * 2. Additional costs (charges, shipping, insurance, tax)
     * 3. Total amount
     *
     * @param  Invoice  $invoice  The invoice models to generate a summary for
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
                    'price_total' => $invoice->charges_amount
                ],
                [
                    'label'       => __('Shipping'),
                    'price_total' => $invoice->shipping_amount
                ],
                [
                    'label'       => __('Insurance'),
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
        $options = [
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
            ]
        ];

        if (Arr::get($invoice->organisation->settings, 'invoice_export.show_omega')) {
            $options[] = [
                'type'       => 'omega',
                'icon'       => 'fas fa-omega',
                'tooltip'    => __('Download Omega'),
                'label'      => 'Omega',
                'name'       => 'grp.org.accounting.invoices.show.omega',
                'parameters' => [
                    'organisation' => $invoice->organisation->slug,
                    'invoice'      => $invoice->slug
                ]
            ];
        }

        return $options;
    }

    public function htmlResponse(Invoice $invoice, ActionRequest $request): Response|RedirectResponse
    {

        if ($invoice->type == InvoiceTypeEnum::REFUND) {

            if ($request->route()->getName() == 'grp.org.accounting.invoices.show') {
                return Redirect::route('grp.org.accounting.refunds.show', [
                    $invoice->organisation->slug,
                    $invoice->slug
                ]);
            }
        }

        if ($invoice->shop->type == ShopTypeEnum::FULFILMENT) {
            return ShowFulfilmentInvoice::make()->htmlResponse($invoice, $request, $this->tab);
        }

        $subNavigation = [];

        $payBoxData           = $this->getPayBoxData($invoice);
        $actions              = $this->getInvoiceActions($invoice, $request, $payBoxData);
        $exportInvoiceOptions = $this->getExportOptions($invoice);


        $deliveryNoteRoute = null;

        /** @var DeliveryNote $firstDeliveryNote */
        $firstDeliveryNote = $invoice->order?->deliveryNotes()->first();

        if ($firstDeliveryNote) {
            $deliveryNoteRoute = [
                'deliveryNoteRoute'    => [
                    'name'       => 'grp.org.shops.show.ordering.orders.show.delivery-note',
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'shop'         => $invoice->shop->slug,
                        'order'        => $invoice->order->slug,
                        'deliveryNote' => $firstDeliveryNote->slug
                    ]
                ],
                'deliveryNotePdfRoute' => [
                    'name'       => 'grp.pdfs.delivery-notes',
                    'parameters' => [
                        'deliveryNote' => $firstDeliveryNote->slug,
                    ],
                ]
            ];
        }


        return Inertia::render(
            'Org/Accounting/Invoice',
            [
                'title'       => __('Invoice'),
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
                'routes'               => [
                    'delivery_note' => $deliveryNoteRoute
                ],

                'box_stats'    => $this->getBoxStats($invoice),
                'list_refunds' => RefundResource::collection($invoice->refunds),
                'invoice'      => InvoiceResource::make($invoice),
                'outbox'       => [
                    'state'          => $invoice->shop->outboxes()->where('code', OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER->value)->first()?->state->value,
                    'workshop_route' => $this->getOutboxRoute($invoice)
                ],

                InvoiceTabsEnum::REFUNDS->value => $this->tab == InvoiceTabsEnum::REFUNDS->value
                    ? fn() => RefundsResource::collection(IndexRefunds::run($invoice, InvoiceTabsEnum::REFUNDS->value))
                    : Inertia::lazy(fn() => RefundsResource::collection(IndexRefunds::run($invoice, InvoiceTabsEnum::REFUNDS->value))),

                InvoiceTabsEnum::INVOICE_TRANSACTIONS->value => $this->tab == InvoiceTabsEnum::INVOICE_TRANSACTIONS->value ?
                    fn() => InvoiceTransactionsResource::collection(IndexInvoiceTransactions::run($invoice, InvoiceTabsEnum::INVOICE_TRANSACTIONS->value))
                    : Inertia::lazy(fn() => InvoiceTransactionsResource::collection(IndexInvoiceTransactions::run($invoice, InvoiceTabsEnum::INVOICE_TRANSACTIONS->value))),


                InvoiceTabsEnum::EMAIL->value => $this->tab == InvoiceTabsEnum::EMAIL->value ?
                    fn() => DispatchedEmailsResource::collection(IndexDispatchedEmails::run($invoice->customer, InvoiceTabsEnum::EMAIL->value))
                    : Inertia::lazy(fn() => DispatchedEmailsResource::collection(IndexDispatchedEmails::run($invoice->customer, InvoiceTabsEnum::EMAIL->value))),


                InvoiceTabsEnum::PAYMENTS->value => $this->tab == InvoiceTabsEnum::PAYMENTS->value ?
                    fn() => PaymentsResource::collection(IndexPayments::run($invoice))
                    : Inertia::lazy(fn() => PaymentsResource::collection(IndexPayments::run($invoice))),

                InvoiceTabsEnum::HISTORY->value => $this->tab == InvoiceTabsEnum::HISTORY->value ?
                    fn() => HistoryResource::collection(IndexHistory::run($invoice))
                    : Inertia::lazy(fn() => HistoryResource::collection(IndexHistory::run($invoice))),

            ]
        )->table(IndexPayments::make()->tableStructure($invoice, [], InvoiceTabsEnum::PAYMENTS->value))
            ->table(IndexRefunds::make()->tableStructure(parent: $invoice, prefix: InvoiceTabsEnum::REFUNDS->value))
            ->table(IndexDispatchedEmails::make()->tableStructure($invoice->customer, prefix: InvoiceTabsEnum::EMAIL->value))
            ->table(IndexHistory::make()->tableStructure(prefix: InvoiceTabsEnum::HISTORY->value))
            ->table(IndexInvoiceTransactions::make()->tableStructure(InvoiceTabsEnum::INVOICE_TRANSACTIONS->value));
    }


    public function jsonResponse(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice);
    }


}

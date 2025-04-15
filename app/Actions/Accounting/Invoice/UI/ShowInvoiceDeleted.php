<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 25-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\InvoiceTransaction\UI\IndexInvoiceTransactionsGroupedByAsset;
use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\UI\Accounting\InvoiceTabsEnum;
use App\Http\Resources\Accounting\InvoiceResource;
use App\Http\Resources\Accounting\InvoiceTransactionsResource;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowInvoiceDeleted extends OrgAction
{
    use IsInvoiceUI;
    use WithFulfilmentCustomerSubNavigation;

    private Organisation|Fulfilment|FulfilmentCustomer|Shop $parent;

    public function handle(Invoice $invoice): Invoice
    {
        return $invoice;
    }


    public function asController(Organisation $organisation, $invoiceSlug, ActionRequest $request): Invoice
    {
        $invoice = Invoice::onlyTrashed()->where('slug', $invoiceSlug)->first();
        if (!$invoice) {
            abort(404);
        }
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(InvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, $invoiceSlug, ActionRequest $request): Invoice
    {
        $invoice = Invoice::onlyTrashed()->where('slug', $invoiceSlug)->first();
        if (!$invoice) {
            abort(404);
        }
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(InvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, $invoiceSlug, ActionRequest $request): Invoice
    {
        $invoice = Invoice::onlyTrashed()->where('slug', $invoiceSlug)->first();
        if (!$invoice) {
            abort(404);
        }
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(InvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, $invoiceSlug, ActionRequest $request): Invoice
    {
        $invoice = Invoice::onlyTrashed()->where('slug', $invoiceSlug)->first();
        if (!$invoice) {
            abort(404);
        }
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(InvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    public function htmlResponse(Invoice $invoice, ActionRequest $request): Response
    {
        $subNavigation = [];
        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        }

        $actions = [];


        return Inertia::render(
            'Org/Accounting/InvoiceDeleted',
            [
                'title'       => __('Deleted Invoice'),
                'breadcrumbs' => ShowInvoice::make()->getBreadcrumbs(
                    $invoice,
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    __('deleted')
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($invoice, $request),
                    'next'     => $this->getNext($invoice, $request),
                ],
                'pageHead'    => [
                    'subNavigation' => $subNavigation,
                    'model'         => __('Deleted Invoice'),
                    'title'         => $invoice->reference,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-file-invoice-dollar'],
                        'title' => $invoice->reference
                    ],
                    'actions'       => $actions
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => InvoiceTabsEnum::navigation()
                ],

                'order_summary' => ShowInvoice::make()->getInvoiceSummary($invoice),


                'exportPdfRoute' => [
                    'name'       => 'grp.org.accounting.invoices.download',
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'invoice'      => $invoice->slug
                    ]
                ],
                'box_stats'      => $this->getBoxStats($invoice),

                'invoice' => InvoiceResource::make($invoice),
                'outbox'  => [
                    'state'          => $invoice->shop->outboxes()->where('code', OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER->value)->first()?->state->value,
                    'workshop_route' => $this->getOutboxRoute($invoice)
                ],

                InvoiceTabsEnum::GROUPED->value => $this->tab == InvoiceTabsEnum::GROUPED->value ?
                    fn () => InvoiceTransactionsResource::collection(IndexInvoiceTransactionsGroupedByAsset::run($invoice, InvoiceTabsEnum::GROUPED->value))
                    : Inertia::lazy(fn () => InvoiceTransactionsResource::collection(IndexInvoiceTransactionsGroupedByAsset::run($invoice, InvoiceTabsEnum::GROUPED->value))),

                InvoiceTabsEnum::PAYMENTS->value => $this->tab == InvoiceTabsEnum::PAYMENTS->value ?
                    fn () => PaymentsResource::collection(IndexPayments::run($invoice))
                    : Inertia::lazy(fn () => PaymentsResource::collection(IndexPayments::run($invoice))),


            ]
        )->table(IndexPayments::make()->tableStructure($invoice, [], InvoiceTabsEnum::PAYMENTS->value))
            ->table(IndexInvoiceTransactionsGroupedByAsset::make()->tableStructure($invoice, InvoiceTabsEnum::GROUPED->value));
    }


    public function jsonResponse(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice);
    }


    public function getPrevious(Invoice $invoice, ActionRequest $request): ?array
    {
        $previous = Invoice::onlyTrashed()->where('reference', '<', $invoice->reference)
            ->where('invoices.shop_id', $invoice->shop_id)
            ->orderBy('reference', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Invoice $invoice, ActionRequest $request): ?array
    {
        $next = Invoice::onlyTrashed()->where('reference', '>', $invoice->reference)
            ->where('invoices.shop_id', $invoice->shop_id)
            ->orderBy('reference')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Invoice $invoice, string $routeName): ?array
    {
        if (!$invoice) {
            return null;
        }

        // $isInvoice = $invoice->type === InvoiceTypeEnum::INVOICE;

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
            'grp.org.accounting.deleted_invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'invoiceId'      => $invoice->slug
                    ]

                ]
            ],
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

            //  'grp.org.fulfilments.show.crm.customers.show.refund.show'
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

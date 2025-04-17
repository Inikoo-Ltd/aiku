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
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\UI\Accounting\FulfilmentInvoiceTabsEnum;
use App\Http\Resources\Accounting\InvoiceTransactionsGroupedByAssetResource;
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
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFulfilmentInvoice extends OrgAction
{
    use IsInvoiceUI;
    use WithInvoicePayBox;
    use WithFulfilmentCustomerSubNavigation;
    use WithFulfilmentShopAuthorisation;

    private Organisation|Fulfilment|FulfilmentCustomer|null $parent;

    public function __construct(ActionRequest $request)
    {
        $this->parent = $this->getParent($request);
    }

    protected function getParent(ActionRequest $request): Organisation|null
    {

        if (!$request->route()) {
            return null;
        }

        $parent    = null;


        $routeName = $request->route()->getName();

        if ($routeName == 'grp.org.accounting.invoices.show') {
            /** @var Organisation $organisation */
            $organisation = $request->route()->parameter('organisation');
            $parent       = $organisation;
        }


        return $parent;
    }

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


    public function htmlResponse(Invoice $invoice, ActionRequest $request, $tab = null): Response
    {
        if ($tab !== null) {
            $this->tab = $tab;
        }

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
                    fn () => InvoiceTransactionsGroupedByAssetResource::collection(IndexInvoiceTransactionsGroupedByAsset::run($invoice, FulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS->value))
                    : Inertia::lazy(fn () => InvoiceTransactionsGroupedByAssetResource::collection(IndexInvoiceTransactionsGroupedByAsset::run($invoice, FulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS->value))),

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






}

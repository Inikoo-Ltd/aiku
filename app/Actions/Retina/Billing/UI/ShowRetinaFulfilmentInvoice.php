<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Feb 2025 15:16:34 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Billing\UI;

use App\Actions\Accounting\InvoiceTransaction\UI\IndexInvoiceTransactions;
use App\Actions\Accounting\InvoiceTransaction\UI\IndexInvoiceTransactionsGroupedByAsset;
use App\Actions\Retina\Accounting\Payment\UI\IndexRetinaPayments;
use App\Actions\RetinaAction;
use App\Enums\UI\Accounting\RetinaFulfilmentInvoiceTabsEnum;
use App\Http\Resources\Accounting\InvoiceResource;
use App\Http\Resources\Accounting\InvoiceTransactionsGroupedByAssetResource;
use App\Http\Resources\Accounting\ItemizedInvoiceTransactionsResource;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Models\Accounting\Invoice;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaFulfilmentInvoice extends RetinaAction
{
    public function handle(Invoice $invoice): Invoice
    {
        return $invoice;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->customer->id == $request->route()->parameter('invoice')->customer_id) {
            return true;
        }

        return false;
    }

    public function asController(Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->initialisation($request)->withTab(RetinaFulfilmentInvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    public function htmlResponse(Invoice $invoice, ActionRequest $request): Response
    {
        $toPayAmount = round($invoice->total_amount - $invoice->payment_amount, 2);

        return Inertia::render(
            'Billing/RetinaInvoice',
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
                    'model' => __('invoice'),
                    'title' => $invoice->reference,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-file-invoice-dollar'],
                        'title' => $invoice->reference
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => RetinaFulfilmentInvoiceTabsEnum::navigation()
                ],

                'order_summary' => [
                    [
                        [
                            'label'       => __('Services'),
                            'price_total' => $invoice->services_amount
                        ],
                        [
                            'label'       => __('Physical Goods'),
                            'price_total' => $invoice->goods_amount
                        ],
                        [
                            'label'       => __('Rental'),
                            'price_total' => $invoice->rental_amount
                        ],
                    ],
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
                            'information' => '(vat)',
                            'price_total' => $invoice->tax_amount
                        ],

                    ],
                    [
                        [
                            'label'       => __('Total'),
                            'price_total' => $invoice->total_amount
                        ],
                    ],
                ],

                'exportPdfRoute'          => [
                    'name'       => 'retina.fulfilment.billing.invoices.download',
                    'parameters' => [
                        'invoice' => $invoice->slug
                    ]
                ],
                'exportTransactionsRoute' => [
                    'name'       => 'retina.fulfilment.billing.invoices.invoice-transactions.export',
                    'parameters' => [
                        'invoice' => $invoice->slug
                    ]
                ],
                'box_stats'               => [
                    'customer'    => [
                        'route'        => [
                            'name'       => 'retina.fulfilment.billing.invoices.show',
                            'parameters' => [
                                'invoice' => $invoice->slug,
                            ]
                        ],
                        'slug'         => $invoice->customer->slug,
                        'reference'    => $invoice->customer->reference,
                        'contact_name' => $invoice->customer->contact_name,
                        'company_name' => $invoice->customer->company_name,
                        'location'     => $invoice->customer->location,
                        'phone'        => $invoice->customer->phone,
                    ],
                    'information' => [
                        'recurring_bill' => [
                            'reference' => $invoice->reference
                        ],
                        'routes'         => [
                        ],
                        'paid_amount'    => $invoice->payment_amount,
                        'pay_amount'     => $toPayAmount
                    ]
                ],

                'invoice' => InvoiceResource::make($invoice),


                RetinaFulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS->value => $this->tab == RetinaFulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS->value ?
                    fn () => InvoiceTransactionsGroupedByAssetResource::collection(IndexInvoiceTransactionsGroupedByAsset::run($invoice, RetinaFulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS->value))
                    : Inertia::lazy(fn () => InvoiceTransactionsGroupedByAssetResource::collection(IndexInvoiceTransactionsGroupedByAsset::run($invoice, RetinaFulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS->value))),

                RetinaFulfilmentInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS->value => $this->tab == RetinaFulfilmentInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS->value
                    ?
                    fn () => tap(
                        ItemizedInvoiceTransactionsResource::collection(IndexInvoiceTransactions::run($invoice, RetinaFulfilmentInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS->value)),
                        function ($resource) {
                            $resource->collection->map(fn (ItemizedInvoiceTransactionsResource $item) => $item->additional([
                                'isRetina' => true
                            ]));
                        }
                    )
                    : Inertia::lazy(
                        fn () => tap(
                            ItemizedInvoiceTransactionsResource::collection(IndexInvoiceTransactions::run($invoice, RetinaFulfilmentInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS->value)),
                            function ($resource) {
                                $resource->collection->map(fn (ItemizedInvoiceTransactionsResource $item) => $item->additional([
                                    'isRetina' => true
                                ]));
                            }
                        )
                    ),

                RetinaFulfilmentInvoiceTabsEnum::PAYMENTS->value => $this->tab == RetinaFulfilmentInvoiceTabsEnum::PAYMENTS->value ?
                    fn () => PaymentsResource::collection(IndexRetinaPayments::run($invoice))
                    : Inertia::lazy(fn () => PaymentsResource::collection(IndexRetinaPayments::run($invoice))),


            ]
        )->table(IndexRetinaPayments::make()->tableStructure($invoice, [], RetinaFulfilmentInvoiceTabsEnum::PAYMENTS->value))
            ->table(IndexInvoiceTransactions::make()->tableStructure(RetinaFulfilmentInvoiceTabsEnum::ITEMIZED_FULFILMENT_INVOICE_TRANSACTIONS->value))
            ->table(IndexInvoiceTransactionsGroupedByAsset::make()->tableStructure($invoice, RetinaFulfilmentInvoiceTabsEnum::GROUPED_FULFILMENT_INVOICE_TRANSACTIONS->value));
    }


    public function getBreadcrumbs(Invoice $invoice, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Invoice $invoice, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Invoices')
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
            'retina.fulfilment.billing.invoices.show' =>
            array_merge(
                ShowRetinaBillingDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'retina.fulfilment.billing.invoices.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'retina.fulfilment.billing.invoices.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }


    public function getPrevious(Invoice $invoice, ActionRequest $request): ?array
    {
        $previous = Invoice::where('reference', '<', $invoice->reference)
            ->where('invoices.customer_id', $invoice->customer_id)
            ->orderBy('reference', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Invoice $invoice, ActionRequest $request): ?array
    {
        $next = Invoice::where('reference', '>', $invoice->reference)
            ->where('invoices.customer_id', $invoice->customer_id)
            ->orderBy('reference')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Invoice $invoice, string $routeName): ?array
    {
        if (!$invoice) {
            return null;
        }

        return match ($routeName) {
            'retina.fulfilment.billing.invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'invoice' => $invoice->slug
                    ]

                ]
            ],
        };
    }
}

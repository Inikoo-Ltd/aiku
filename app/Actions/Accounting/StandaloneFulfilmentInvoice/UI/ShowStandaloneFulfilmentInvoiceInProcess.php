<?php

/*
 * author Arya Permana - Kirin
 * created on 27-02-2025-09h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\StandaloneFulfilmentInvoice\UI;

use App\Actions\Accounting\Invoice\UI\IsInvoiceUI;
use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction\UI\IndexStandaloneFulfilmentInvoiceTransactions;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\UI\Accounting\InvoiceTabsEnum;
use App\Enums\UI\Accounting\StandaloneFulfilmentInvoiceTabsEnum;
use App\Http\Resources\Accounting\InvoiceResource;
use App\Http\Resources\Accounting\StandaloneFulfilmentInvoiceTransactionsResource;
use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStandaloneFulfilmentInvoiceInProcess extends OrgAction
{
    use IsInvoiceUI;
    use WithFulfilmentCustomerSubNavigation;

    private FulfilmentCustomer $parent;

    public function handle(Invoice $invoice): Invoice
    {
        return $invoice;
    }
    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(StandaloneFulfilmentInvoiceTabsEnum::values());

        return $this->handle($invoice);
    }

    public function htmlResponse(Invoice $invoice, ActionRequest $request): Response
    {
        $disable = true;
        $tooltip = 'add at least one transaction to complete';
        if ($invoice->invoiceTransactions()->count() > 0) {
            $disable = false;
            $tooltip = 'complete invoice';
        }
        $navigation = StandaloneFulfilmentInvoiceTabsEnum::navigation();

        $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);

        $serviceRoute = [
            'name'       => 'grp.json.fulfilment.invoice.services.index',
            'parameters' => [
                'fulfilment' => $invoice->shop->fulfilment->slug,
                'scope'      => $invoice->slug
            ]
        ];

        $productRoute = [
            'name'       => 'grp.json.fulfilment.invoice.physical-goods.index',
            'parameters' => [
                'fulfilment' => $invoice->shop->fulfilment->slug,
                'scope'      => $invoice->slug
            ]
        ];

        $palletsRoute = [
            'name' => 'grp.json.fulfilment-customer.storing-pallets.index',
            'parameters' => [
                'fulfilmentCustomer' => $invoice->customer->fulfilmentCustomer->slug
            ]
        ];

        $actions = [];

        $actions[] =
        [
            'type'    => 'button',
            'style'   => 'red_outline',
            'tooltip' => __('delete'),
            'icon'    => 'fal fa-trash-alt',
            'key'     => 'delete_booked_in',
            'ask_why' => true,
            'route'   => [
                'method'     => 'delete',
                'name'       => 'grp.models.invoice.in-process.delete',
                'parameters' => [
                    'invoice' => $invoice->id
                ]
            ]
        ];

        $actions[] = [
            'type'    => 'button',
            'style'   => 'secondary',
            'icon'    => 'fal fa-plus',
            'key'     => 'add-service',
            'label'   => __('add service'),
            'tooltip' => __('Add single service'),
            'route'   => [
                'name'       => 'grp.models.standalone-invoice.transaction.store',
                'parameters' => [
                    'invoice' => $invoice->id
                ]
            ]
        ];
        $actions[]  =   [
            'type'    => 'button',
            'style'   => 'secondary',
            'icon'    => 'fal fa-plus',
            'key'     => 'add-physical-good',
            'label'   => __('add physical good'),
            'tooltip' => __('Add physical good'),
            'route'   => [
                'name'       => 'grp.models.standalone-invoice.transaction.store',
                'parameters' => [
                    'invoice' => $invoice->id
                ]
            ]
        ];
        $actions[] =
            [
                'type'  => 'button',
                // 'style' => 'tertiary',
                'label' => __('complete invoice'),
                'key'   => 'send-invoice',
                'disabled' => $disable,
                'tooltip'  => $tooltip,
                'route' => [
                    'method'     => 'post',
                    'name'       => 'grp.models.standalone-invoice.complete',
                    'parameters' => [
                        'invoice' => $invoice->id
                    ]
                ]
            ];

        return Inertia::render(
            'Org/Accounting/InvoiceManual',
            [
                'title'       => __('invoice'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'subNavigation' => $subNavigation,
                    'model'         => __('invoice'),
                    'title'         => $invoice->reference,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-file-invoice-dollar'],
                        'title' => $invoice->reference
                    ],
                    'iconRight' => $invoice->in_process ? [
                        'icon' => 'fal fa-seedling',
                        'tooltip' => __('In Process'),
                        'class' => 'text-green-500'
                    ] : null,
                    'actions'       => $actions
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
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
                ],

                'exportPdfRoute' => [
                    'name'       => 'grp.org.accounting.invoices.download',
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'invoice'      => $invoice->slug
                    ]
                ],
                'box_stats'      => $this->getBoxStats($invoice),

                'service_list_route'       => $serviceRoute,
                'physical_good_list_route' => $productRoute,
                'pallet_list_route' => $palletsRoute,

                'invoice' => InvoiceResource::make($invoice),
                'outbox'  => [
                    'state'          => $invoice->shop->outboxes()->where('code', OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER->value)->first()?->state->value,
                    'workshop_route' => $this->getOutboxRoute($invoice)
                ],

                StandaloneFulfilmentInvoiceTabsEnum::ITEMS->value => $this->tab == StandaloneFulfilmentInvoiceTabsEnum::ITEMS->value ?
                    fn () => StandaloneFulfilmentInvoiceTransactionsResource::collection(IndexStandaloneFulfilmentInvoiceTransactions::run($invoice, StandaloneFulfilmentInvoiceTabsEnum::ITEMS->value))
                    : Inertia::lazy(fn () => StandaloneFulfilmentInvoiceTransactionsResource::collection(IndexStandaloneFulfilmentInvoiceTransactions::run($invoice, StandaloneFulfilmentInvoiceTabsEnum::ITEMS->value))),
            ]
        )->table(IndexPayments::make()->tableStructure($invoice, [], InvoiceTabsEnum::PAYMENTS->value))
            ->table(IndexStandaloneFulfilmentInvoiceTransactions::make()->tableStructure(StandaloneFulfilmentInvoiceTabsEnum::ITEMS->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
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
        $invoice   = Invoice::where('slug', $routeParameters['invoice'])->first();

        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show.invoices.in-process.show',
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
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.in-process.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer', 'invoice'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }
}

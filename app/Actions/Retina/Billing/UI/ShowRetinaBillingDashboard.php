<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 May 2024 10:42:05 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Billing\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Models\CRM\Customer;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowRetinaBillingDashboard
{
    use AsAction;

    public function asController(ActionRequest $request): Response
    {

        /** @var Customer $customer */
        $customer = $request->user()->customer;
        $currentRecurringBill = $customer->fulfilmentCustomer?->currentRecurringBill;
        $numberUnpaidInvoices = $customer->stats->number_unpaid_invoices;
        $amountUnpaidInvoices = $customer->stats->unpaid_invoices_amount;
        $numberInvoices = $customer->stats->number_invoices;

        return Inertia::render(
            'Billing/RetinaBillingDashboard',
            [
                'title' => __('Billing'),
                'breadcrumbs' => $this->getBreadcrumbs(),
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'fa-file-invoice-dollar'],
                        'title' => __('Billing'),
                    ],
                    'title' => __('Billing'),
                ],
                'dashboard_stats' => [
                    'settings' => auth()->user()->settings,
                    'columns' => [
                        [
                            'widgets' => [
                                [
                                    'type' => 'unpaid_invoices',
                                    'data' => GetDataTableRetinaBillingDashboard::run($customer),
                                ],
                            ],
                        ],
                        [
                            'widgets' => [
                                $currentRecurringBill ?
                                [
                                    'label' => __('Next bill'),
                                    'data' => [
                                        [
                                            'label' => __('current total'),
                                            'route' => route('retina.fulfilment.billing.next_recurring_bill'),
                                            'value' => $currentRecurringBill->total_amount, // <-- need to be currency
                                            'type' => 'card_currency_success',
                                        ],
                                        [
                                            'label' => __('To be invoiced at'),
                                            'value' => $currentRecurringBill->end_date, // a date
                                            'type' => 'date',
                                        ],
                                    ],
                                    'type' => 'multi_card',
                                ] : null,
                                $numberInvoices ?
                                [
                                    'label' => __('Total Invoices'),
                                    'route' => route('retina.fulfilment.billing.invoices.index'),
                                    'value' => $numberInvoices,
                                    'type' => 'card_number',
                                ] : null,
                            ],
                        ],
                        [
                            'widgets' => [
                                $numberUnpaidInvoices ?
                                [
                                    'label' => __('Total Unpaid Invoices'),
                                    'value' => $numberUnpaidInvoices,
                                    'type' => 'card_number_attention',
                                ] : null,
                                $amountUnpaidInvoices ?
                                [
                                    'label' => __('Amount Unpaid Invoices'),
                                    'value' => $amountUnpaidInvoices,
                                    'type' => 'card_number_attention',
                                ] : null,
                            ],
                        ],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.fulfilment.billing.dashboard',
                            ],
                            'label' => __('Billing'),
                        ],
                    ],
                ]
            );
    }
}

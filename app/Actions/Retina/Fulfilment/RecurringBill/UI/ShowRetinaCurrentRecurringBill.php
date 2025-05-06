<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 16:54:01 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\RecurringBill\UI;

use App\Actions\Fulfilment\RecurringBill\UI\ShowRecurringBill;
use App\Actions\Retina\Billing\UI\ShowRetinaBillingDashboard;
use App\Actions\RetinaAction;
use App\Enums\UI\Fulfilment\RecurringBillTabsEnum;
use App\Http\Resources\Fulfilment\RetinaRecurringBillTransactionsResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\StoredItem;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property StoredItem $storedItem
 */
class ShowRetinaCurrentRecurringBill extends RetinaAction
{
    public function asController(ActionRequest $request): RecurringBill
    {
        $this->initialisation($request)->withTab(RecurringBillTabsEnum::values());
        $currentRecurringBill = $this->customer->fulfilmentCustomer->currentRecurringBill;

        return $this->handle($currentRecurringBill);
    }

    public function handle(RecurringBill $recurringBill): RecurringBill
    {
        return $recurringBill;
    }

    public function htmlResponse(RecurringBill $recurringBill, ActionRequest $request): Response
    {
        $navigation = RecurringBillTabsEnum::navigation();
        unset($navigation[RecurringBillTabsEnum::HISTORY->value]);


        return Inertia::render(
            'Billing/RetinaRecurringBill',
            [
                'title'       => __('recurring bill'),
                'breadcrumbs' => $this->getBreadcrumbs(),
                'pageHead'    => [
                    'icon'  =>
                        [
                            'icon'  => ['fa', 'fa-receipt'],
                            'title' => __('recurring bill')
                        ],
                    'model' => __('Bill'),
                    'title' => $recurringBill->slug,
                    'noCapitalise' => true
                ],
                'timeline_rb' => [
                    'start_date' => $recurringBill->start_date,
                    'end_date'   => $recurringBill->end_date
                ],
                'status_rb'   => $recurringBill->status,
                'currency'    => CurrencyResource::make($recurringBill->currency),
                'box_stats'   => ShowRecurringBill::make()->getRecurringBillBoxStats($recurringBill, $this->fulfilmentCustomer),
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],

                RecurringBillTabsEnum::TRANSACTIONS->value => $this->tab == RecurringBillTabsEnum::TRANSACTIONS->value ?
                    fn () => RetinaRecurringBillTransactionsResource::collection(IndexRetinaRecurringBillTransactions::run($recurringBill, RecurringBillTabsEnum::TRANSACTIONS->value))
                    : Inertia::lazy(fn () => RetinaRecurringBillTransactionsResource::collection(IndexRetinaRecurringBillTransactions::run($recurringBill, RecurringBillTabsEnum::TRANSACTIONS->value))),

            ]
        )->table(
            IndexRetinaRecurringBillTransactions::make()->tableStructure(
                $recurringBill,
                prefix: RecurringBillTabsEnum::TRANSACTIONS->value
            )
        );
    }


    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowRetinaBillingDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-receipt',
                        'label' => __('Next Bill'),
                        'route' => [
                            'name' => 'retina.fulfilment.billing.next_recurring_bill'
                        ]
                    ]

                ]
            ],
        );
    }
}

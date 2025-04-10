<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Feb 2025 12:52:08 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Traits\Authorisations\WithAccountingAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

trait IsInvoiceUI
{
    use WithAccountingAuthorisation;
    public function authorize(ActionRequest $request): bool
    {

        return true;
        if ($this->parent instanceof Organisation) {
            return $request->user()->authTo("accounting.{$this->organisation->id}.view");
        } elseif ($this->parent instanceof Shop) {
            //todo think about it
            return false;
        } elseif ($this->parent instanceof Fulfilment) {

            $this->isSupervisor = $request->user()->authTo([
                "supervisor-fulfilment-shop.".$this->fulfilment->id
            ]);

            return $request->user()->authTo(
                [
                    "fulfilment-shop.{$this->fulfilment->id}.view",
                    "accounting.{$this->fulfilment->organisation_id}.view"
                ]
            );
        } elseif ($this->parent instanceof FulfilmentCustomer) {
            $this->isSupervisor = $request->user()->authTo([
                "supervisor-fulfilment-shop.".$this->fulfilment->id
            ]);
            
            return $request->user()->authTo(
                [
                    "fulfilment-shop.{$this->fulfilment->id}.view",
                    "accounting.{$this->fulfilment->organisation_id}.view"
                ]
            );
        }

        return false;
    }

    public function getCustomerRoute(Invoice $invoice): array
    {
        if ($this->parent instanceof Fulfilment) {
            $customerRoute = [
                'name' => 'grp.org.fulfilments.show.crm.customers.show',
                'parameters' => [
                    'organisation' => $invoice->organisation->slug,
                    'fulfilment' => $invoice->customer->fulfilmentCustomer->fulfilment->slug,
                    'fulfilmentCustomer' => $invoice->customer->fulfilmentCustomer->slug,
                ]
            ];
        } else {
            $customerRoute = [
                'name' => 'grp.org.shops.show.crm.customers.show',
                'parameters' => [
                    'organisation' => $invoice->organisation->slug,
                    'shop' => $invoice->shop->slug,
                    'customer' => $invoice->customer->slug,
                ]
            ];
        }

        return $customerRoute;
    }

    public function getOutboxRoute(Invoice $invoice): array
    {
        /** @var Outbox $outbox */
        $outbox = $invoice->shop->outboxes()->where('code', OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER->value)->first();

        if ($invoice->shop->type === ShopTypeEnum::FULFILMENT) {
            return [
                'name'       => 'grp.org.fulfilments.show.operations.comms.outboxes.workshop',
                'parameters' => [
                    'organisation' => $invoice->organisation->slug,
                    'fulfilment'   => $invoice->customer->fulfilmentCustomer->fulfilment->slug,
                    'outbox'       => $outbox->slug
                ]
            ];
        }

        return [
            'name'       => 'grp.org.shops.show.dashboard.comms.outboxes.workshop',
            'parameters' => [
                'organisation' => $invoice->organisation->slug,
                'shop'   => $invoice->customer->shop->slug,
                'outbox'       => $outbox->slug
            ]
        ];
    }

    public function getRecurringBillRoute(Invoice $invoice): ?array
    {
        if ($invoice->shop->type !== ShopTypeEnum::FULFILMENT) {
            return  null;
        }
        $recurringBillRoute = null;
        if ($invoice->recurringBill()->exists()) {
            if ($this->parent instanceof Fulfilment) {
                $recurringBillRoute = [
                    'name' => 'grp.org.fulfilments.show.operations.recurring_bills.show',
                    'parameters' => [$invoice->organisation->slug, $this->parent->slug, $invoice->recurringBill->slug]
                ];
            } elseif ($this->parent instanceof FulfilmentCustomer) {
                $recurringBillRoute = [
                    'name' => 'grp.org.fulfilments.show.crm.customers.show.recurring_bills.show',
                    'parameters' => [$invoice->organisation->slug, $this->parent->fulfilment->slug, $this->parent->slug, $invoice->recurringBill->slug]
                ];
            }
        }

        return $recurringBillRoute;
    }

    public function getBoxStats(Invoice $invoice): array
    {
        return  [
            'customer'    => [
                'slug'         => $invoice->customer->slug,
                'reference'    => $invoice->customer->reference,
                'route'        => $this->getCustomerRoute($invoice),
                'contact_name' => $invoice->customer->contact_name,
                'company_name' => $invoice->customer->company_name,
                'location'     => $invoice->customer->location,
                'phone'        => $invoice->customer->phone,
                // 'address'      => AddressResource::collection($invoice->customer->addresses),
            ],
            'information' => [
                'recurring_bill' => [
                    'reference' => $invoice->reference,
                    'route'     => $this->getRecurringBillRoute($invoice)
                ],
                'paid_amount'    => $invoice->payment_amount,
                'pay_amount'     => round($invoice->total_amount - $invoice->payment_amount, 2)
            ]
        ];
    }

}

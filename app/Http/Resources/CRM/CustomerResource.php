<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Apr 2024 14:30:49 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\TaxNumberResource;
use App\Models\CRM\Customer;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $number_current_customer_clients
 */
class CustomerResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Customer $customer */
        $customer = $this;

        $comms = $customer->comms;

        $subscriptions = [
            'newsletters' => [
                'label'=>__('Newsletters'),
                'is_subscribed'   => $comms->is_subscribed_to_newsletter,
                'unsubscribed_at' => $comms->newsletter_unsubscribed_at
            ],
            'marketing'   => [
                'label'=>__('Marketing'),
                'is_subscribed'   => $comms->is_subscribed_to_marketing,
                'unsubscribed_at' => $comms->marketing_unsubscribed_at
            ],

        ];

        if ($customer->shop->type == ShopTypeEnum::B2B) {
            $subscriptions['abandoned_cart'] = [
                'label'=>__('Abandoned Cart'),
                'is_subscribed'   => $comms->is_subscribed_to_abandoned_cart,
                'unsubscribed_at' => $comms->abandoned_cart_unsubscribed_at
            ];


            $subscriptions['reorder_reminder'] = [
                'label'=>__('Reorder Reminder'),
                'is_subscribed'   => $comms->is_subscribed_to_reorder_reminder,
                'unsubscribed_at' => $comms->reorder_reminder_unsubscribed_at
            ];

            $subscriptions['basket_low_stock'] = [
                'label'=>__('Basket Low Stock'),
                'is_subscribed'   => $comms->is_subscribed_to_basket_low_stock,
                'unsubscribed_at' => $comms->basket_low_stock_unsubscribed_at
            ];

            $subscriptions['to_basket_reminder'] = [
                'label'=>__('To Basket Reminder'),
                'is_subscribed'   => $comms->is_subscribed_to_basket_reminder,
                'unsubscribed_at' => $comms->basket_reminder_unsubscribed_at
            ];
        }


        return [
            'id'                  => $customer->id,
            'slug'                => $customer->slug,
            'reference'           => $customer->reference,
            'name'                => $customer->name,
            'contact_name'        => $customer->contact_name,
            'company_name'        => $customer->company_name,
            'location'            => $customer->location,
            'address'             => AddressResource::make($customer->address),
            'email'               => $customer->email,
            'phone'               => $customer->phone,
            'created_at'          => $customer->created_at,
            'balance'             => $customer->balance,
            'tax_number'          => $customer->taxNumber ? TaxNumberResource::make($customer->taxNumber)->getArray() : [],
            'state'               => $customer->state,
            'status'              => $customer->status,
            'email_subscriptions' => [
                'suspended'     => [
                    'label'           => __('Email communications suspended'),
                    'is_suspended'    => $comms->is_suspended,
                    'suspended_at'    => $comms->suspended_at,
                    'suspended_cause' => $comms->suspended_cause,
                ],
                'subscriptions' => $subscriptions

            ]
        ];
    }
}

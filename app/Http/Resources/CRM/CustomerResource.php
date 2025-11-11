<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Apr 2024 14:30:49 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Catalogue\TagsResource;
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

        // Temp fix || Need to redo later, some payment have invalid customer id
        if(!$customer->resource) return [];

        $comms = $customer->comms;

        $subscriptions = [
            'newsletters' => [
                'label'           => __('Newsletters'),
                'field'           => 'is_subscribed_to_newsletter',
                'is_subscribed'   => $comms->is_subscribed_to_newsletter,
                'unsubscribed_at' => $comms->newsletter_unsubscribed_at
            ],
            'marketing'   => [
                'label'           => __('Marketing'),
                'field'           => 'is_subscribed_to_marketing',
                'is_subscribed'   => $comms->is_subscribed_to_marketing,
                'unsubscribed_at' => $comms->marketing_unsubscribed_at
            ],

        ];

        if ($customer->shop->type == ShopTypeEnum::B2B) {
            $subscriptions['abandoned_cart'] = [
                'label'           => __('Abandoned Cart'),
                'field'           => 'is_subscribed_to_abandoned_cart',
                'is_subscribed'   => $comms->is_subscribed_to_abandoned_cart,
                'unsubscribed_at' => $comms->abandoned_cart_unsubscribed_at
            ];


            $subscriptions['reorder_reminder'] = [
                'label'           => __('Reorder Reminder'),
                'field'           => 'is_subscribed_to_reorder_reminder',
                'is_subscribed'   => $comms->is_subscribed_to_reorder_reminder,
                'unsubscribed_at' => $comms->reorder_reminder_unsubscribed_at
            ];

            $subscriptions['basket_low_stock'] = [
                'label'           => __('Basket Low Stock'),
                'field'           => 'is_subscribed_to_basket_low_stock',
                'is_subscribed'   => $comms->is_subscribed_to_basket_low_stock,
                'unsubscribed_at' => $comms->basket_low_stock_unsubscribed_at
            ];

            $subscriptions['to_basket_reminder'] = [
                'label'           => __('To Basket Reminder'),
                'field'           => 'is_subscribed_to_basket_reminder',
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
            'delivery_address'    => AddressResource::make($customer->deliveryAddress),
            'address_id'          => $customer->address_id,
            'delivery_address_id' => $customer->delivery_address_id,
            'email'               => $customer->email,
            'phone'               => $customer->phone,
            'created_at'          => $customer->created_at,
            'balance'             => $customer->balance,
            'tax_number'          => $customer->taxNumber ? TaxNumberResource::make($customer->taxNumber)->getArray() : [],
            'state'               => $customer->state,
            'status'              => $customer->status,
            'currency_code'       => $customer->shop?->currency?->code,
            'email_subscriptions' => [
                'update_route'  => [
                    'method'     => 'patch',
                    'name'       => match (class_basename(request()->user())) {
                        'WebUser' => 'retina.models.customer_comms.update',
                        default => 'grp.models.customer_comms.update'
                    },
                    'parameters' => [
                        $customer->comms->id
                    ]
                ],
                'suspended'     => [
                    'label'           => __('Email communications suspended'),
                    'is_suspended'    => $comms->is_suspended,
                    'suspended_at'    => $comms->suspended_at,
                    'suspended_cause' => $comms->suspended_cause,
                ],
                'subscriptions' => $subscriptions

            ],
            'tags' => TagsResource::collection($customer->tags)->toArray(request())
        ];
    }
}

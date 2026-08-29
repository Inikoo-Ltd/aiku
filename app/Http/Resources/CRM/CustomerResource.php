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

        // Temp fix || Need to redo later, some payment has invalid customer id
        if (!$customer->resource) {
            return [];
        }

        $comms = $customer->comms;

        $subscriptions = [
            'newsletters' => [
                'label'           => __('Newsletters & Updates'),
                'field'           => 'is_subscribed_to_newsletter',
                'is_subscribed'   => $comms->is_subscribed_to_newsletter,
                'unsubscribed_at' => $comms->newsletter_unsubscribed_at
            ],
            'marketing'   => [
                'label'           => __('New Arrivals & Exclusive Offers'),
                'field'           => 'is_subscribed_to_marketing',
                'is_subscribed'   => $comms->is_subscribed_to_marketing,
                'unsubscribed_at' => $comms->marketing_unsubscribed_at
            ],

        ];

        $shop = $customer->shop;


        if ($shop?->type == ShopTypeEnum::DROPSHIPPING) {
            $subscriptions['price_change_notification'] = [
                'label'           => __('Price Change Notification'),
                'field'           => 'is_subscribed_to_price_change_notification',
                'is_subscribed'   => $comms->is_subscribed_to_price_change_notification,
                'unsubscribed_at' => $comms->price_change_notification_unsubscribed_at
            ];
        }


        return [
            'id'                            => $customer->id,
            'slug'                          => $customer->slug,
            'organisation_slug'             => $customer->organisation?->slug,
            'shop_slug'                     => $shop?->slug,
            'reference'                     => $customer->reference,
            'name'                          => $customer->name,
            'contact_name'                  => $customer->contact_name,
            'company_name'                  => $customer->company_name,
            'fiscal_name'                   => $customer->fiscal_name,
            'location'                      => $customer->location,
            'address'                       => AddressResource::make($customer->address),
            'delivery_address'              => AddressResource::make($customer->deliveryAddress),
            'address_id'                    => $customer->address_id,
            'delivery_address_id'           => $customer->delivery_address_id,
            'email'                         => $customer->email,
            'phone'                         => $customer->phone,
            'contact_website'               => $customer->contact_website,
            'created_at'                    => $customer->created_at,
            'balance'                       => $customer->balance,
            'tax_number'                    => $customer->taxNumber ? TaxNumberResource::make($customer->taxNumber)->getArray() : [],
            'state'                         => $customer->state,
            'status'                        => $customer->status,
            'currency_code'                 => $shop?->currency?->code,
            'identity_document_number'      => $customer->identity_document_number ? [
                'label'     => data_get($shop?->settings, 'customer.identity_document_number') ?? __('Identity document number'),
                'number'    => $customer->identity_document_number,
            ] : null,
            'identity_document_number_alt'  => $customer->identity_document_number_alt ? [
                'label'     => data_get($shop?->settings, 'customer.identity_document_number_alt') ?? __('Identity document number Alt'),
                'number'    => $customer->identity_document_number_alt,
            ] : null,
            'email_subscriptions'           => [
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
            'tags'                          => TagsResource::collection($customer->tags)->toArray(request())
        ];
    }
}

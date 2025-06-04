<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class CallbackRetinaWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'retina:ds:callback-woo {customer} {store_url} {consumer_key} {consumer_secret}';

    public function handle(WooCommerceUser $wooCommerceUser, array $modelData): WooCommerceUser
    {
        $consumerKey = Arr::get($modelData, 'consumer_key');
        $consumerSecret = Arr::get($modelData, 'consumer_secret');

        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = UpdateWooCommerceUser::run($wooCommerceUser, [
            'settings' => [
                'credentials' => [
                    'consumer_key' => $consumerKey,
                    'consumer_secret' => $consumerSecret,
                    'store_url' => Arr::get($wooCommerceUser->settings, 'credentials.store_url')
                ]
            ]
        ]);

        $wooCommerceUser->refresh();

        $webhooks = $wooCommerceUser->registerWooCommerceWebhooks();

        UpdateCustomerSalesChannel::run($wooCommerceUser->customerSalesChannel, [
            'state' => CustomerSalesChannelStateEnum::AUTHENTICATED
        ]);

        return $this->update($wooCommerceUser, [
            'settings' => array_merge($wooCommerceUser->settings, [
                'webhooks' => $webhooks
            ])
        ]);
    }

    public function htmlResponse(WooCommerceUser $wooCommerceUser): Response
    {
        $routeName = match ($wooCommerceUser->customer->is_fulfilment) {
            true => 'retina.fulfilment.dropshipping.customer_sales_channels.show',
            default => 'retina.dropshipping.customer_sales_channels.show'
        };

        return Inertia::location(route($routeName, [
            'customerSalesChannel' => $wooCommerceUser->customerSalesChannel->slug
        ]));
    }

    public function rules(): array
    {
        return [
            'consumer_key' => ['required', 'string'],
            'consumer_secret' => ['required', 'string']
        ];
    }

    public function asController(ActionRequest $request): string
    {
        $wooCommerceUser = WooCommerceUser::findOrFail($request->get('user_id'));
        $this->initialisationFromShop($wooCommerceUser->customer->shop, $request);

        return $this->handle($wooCommerceUser, $this->validatedData);
    }

    public function asCommand(Command $command): void
    {
        $modelData = [
            'store_url' => $command->argument('store_url'),
            'consumer_key' => $command->argument('consumer_key'),
            'consumer_secret' => $command->argument('consumer_secret')
        ];

        $customer = Customer::find($command->argument('customer'));

        data_set($modelData, 'name', $customer->name);

        $this->handle($customer, $modelData);
    }
}

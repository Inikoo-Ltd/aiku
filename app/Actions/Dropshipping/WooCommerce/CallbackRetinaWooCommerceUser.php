<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
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

    public function handle(Customer $customer, $modelData): CustomerSalesChannel
    {
        $name = Arr::get($modelData, 'name');
        $consumerKey = Arr::get($modelData, 'consumer_key');
        $consumerSecret = Arr::get($modelData, 'consumer_secret');
        $storeUrl = Arr::get($modelData, 'store_url');

        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = StoreWooCommerceUser::run($customer, [
            'name' => $name,
            'store_url' => $storeUrl,
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret
        ]);

        $platform = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE)->first();
        $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
            'platform_user_type' => $wooCommerceUser->getMorphClass(),
            'platform_user_id' => $wooCommerceUser->id,
            'reference' => $name
        ]);

        $webhooks = $wooCommerceUser->registerWooCommerceWebhooks();
        $this->update($wooCommerceUser, [
            'customer_sales_channel_id' => $customerSalesChannel->id,
            'settings.webhooks' => $webhooks
        ]);

        return $customerSalesChannel;
    }

    public function htmlResponse(CustomerSalesChannel $customerSalesChannel): Response
    {
        $routeName = match ($customerSalesChannel->customer->is_fulfilment) {
            true => 'retina.fulfilment.dropshipping.customer_sales_channels.show',
            default => 'retina.dropshipping.customer_sales_channels.show'
        };

        return Inertia::location(route($routeName, [
            'customerSalesChannel' => $customerSalesChannel->slug
        ]));
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return [
            'store_url' => ['required', 'string'],
            'consumer_key' => ['required', 'string'],
            'consumer_secret' => ['required', 'string']
        ];
    }

    public function asController(ActionRequest $request): string
    {
        $customer = $request->user()->customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
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

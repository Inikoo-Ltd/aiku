<?php
/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class CallbackRetinaEbayUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'retina:ds:callback-ebay {customer} {store_url} {consumer_key} {consumer_secret}';

    public function handle(EbayUser $ebayUser, array $modelData): EbayUser
    {
        $consumerKey = Arr::get($modelData, 'consumer_key');
        $consumerSecret = Arr::get($modelData, 'consumer_secret');

        /** @var EbayUser $ebayUser */
        $ebayUser = UpdateEbayUser::run($ebayUser, [
            'settings' => [
                'credentials' => [
                    'consumer_key' => $consumerKey,
                    'consumer_secret' => $consumerSecret,
                    'store_url' => Arr::get($ebayUser->settings, 'credentials.store_url')
                ]
            ]
        ]);

        $ebayUser->refresh();

        // $webhooks = $ebayUser->registerWooCommerceWebhooks(); //TODO

        UpdateCustomerSalesChannel::run($ebayUser->customerSalesChannel, [
            'state' => CustomerSalesChannelStateEnum::AUTHENTICATED
        ]);

        return $this->update($ebayUser, [
            'settings' => array_merge($ebayUser->settings, [
                // 'webhooks' => $webhooks//TODO
            ])
        ]);
    }

    public function htmlResponse(EbayUser $ebayUser): Response
    {
        $routeName = match ($ebayUser->customer->is_fulfilment) {
            true => 'retina.fulfilment.dropshipping.customer_sales_channels.show',
            default => 'retina.dropshipping.customer_sales_channels.show'
        };

        return Inertia::location(route($routeName, [
            'customerSalesChannel' => $ebayUser->customerSalesChannel->slug
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
        $ebayUser = EbayUser::findOrFail($request->get('user_id'));
        $this->initialisationFromShop($ebayUser->customer->shop, $request);

        return $this->handle($ebayUser, $this->validatedData);
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

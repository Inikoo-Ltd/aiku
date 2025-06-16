<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\Amazon;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\AmazonUser;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreAmazonUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'retina:ds:store-amazon-user {customer} {name} {data?}';

    public function handle(Customer $customer, array $modelData): AmazonUser
    {
        $platform = Platform::where('type', PlatformTypeEnum::AMAZON->value)->first();

        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'name', Arr::get($modelData, 'name'));
        data_set($modelData, 'settings.credentials.store_url', Arr::pull($modelData, 'store_url'));
        data_set($modelData, 'platform_id', $platform->id);

        /** @var AmazonUser $amazonUser */
        $amazonUser = $customer->amazonUsers()->create($modelData);

        $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
            'platform_user_type' => $amazonUser->getMorphClass(),
            'platform_user_id' => $amazonUser->id,
            'reference' => $amazonUser->name,
            'name' => $amazonUser->name
        ]);

        $amazonUser->update([
            'customer_sales_channel_id' => $customerSalesChannel->id,
        ]);

        return $amazonUser;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'slug'     => ['sometimes', 'string', 'max:255'],
            'data'     => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
        ];
    }

    public function asController(ActionRequest $request): AmazonUser
    {
        $this->initialisationFromShop($request->user()->customer->shop, $request);
        $modelData = $request->validated();

        return $this->handle($request->user()->customer, $modelData);
    }

    public function htmlResponse(AmazonUser $amazonUser): RedirectResponse
    {
        return redirect()->route(
            'org.crm.shop.show',
            [
                'shop' => $amazonUser->shop->slug
            ]
        );
    }

    public function asCommand(Command $command): int
    {
        $customer = Customer::where('slug', $command->argument('customer'))->first();
        $modelData = [
            'name' => $command->argument('name')
        ];

        if ($command->argument('data')) {
            $modelData = array_merge($modelData, json_decode($command->argument('data'), true));
        }

        $this->handle($customer, $modelData);

        return 0;
    }
}

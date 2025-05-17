<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\ShopifyUser;

use App\Actions\CRM\Customer\AttachCustomerToPlatform;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaShopifyUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, $modelData): void
    {
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'username', Str::random(4));
        data_set($modelData, 'password', Str::random(8));

        $shopifyUserNeedParent = ShopifyUser::whereNull('customer_id')
            ->where('name', Arr::get($modelData, 'name'))->first();

        if ($shopifyUserNeedParent) {
            data_set($modelData, 'customer_id', $customer->id);
            $this->update($shopifyUserNeedParent, $modelData);
        } else {
            $customer->shopifyUser()->create($modelData);
        }

        AttachCustomerToPlatform::make()->action($customer, Platform::where('type', PlatformTypeEnum::SHOPIFY->value)->first(), []);
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
            'name' => ['required', 'string', 'max:255', 'ends_with:.' . config('shopify-app.my_shopify_domain'), Rule::unique('shopify_users', 'name')->whereNotNull('customer_id')]
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $shopifyFullName = $request->input('name').'.'.config('shopify-app.my_shopify_domain');

        $this->set('name', $shopifyFullName);
    }

    public function asController(ActionRequest $request): void
    {
        $customer = $request->user()->customer;
        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer, $this->validatedData);
    }
}

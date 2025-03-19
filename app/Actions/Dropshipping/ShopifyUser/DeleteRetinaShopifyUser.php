<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\ShopifyUser;

use App\Actions\CRM\Customer\DetachCustomerToPlatform;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteRetinaShopifyUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(ShopifyUser $shopifyUser)
    {
        $this->update($shopifyUser, [
            'name' => $shopifyUser->name . '-deleted-' . rand(00, 99),
            'slug' => $shopifyUser->slug . '-deleted-' . rand(00, 99),
            'email' => $shopifyUser->email . '-deleted-' . rand(00, 99),
            'status' => false
        ]);

        DetachCustomerToPlatform::run($shopifyUser->customer, Platform::where('type', PlatformTypeEnum::SHOPIFY->value)->first());

        $shopifyUser->delete();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function asController(ActionRequest $request): void
    {
        /** @var \App\Models\CRM\Customer $customer */
        $customer = $request->user()->customer;

        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer->shopifyUser);
    }

    public function inWebhook(ActionRequest $request): void
    {
        $shopifyUser = ShopifyUser::where('name', $request->input('domain'))->first();

        $this->handle($shopifyUser);
    }
}

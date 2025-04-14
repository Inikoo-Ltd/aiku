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
use Random\RandomException;

class DeleteRetinaShopifyUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws RandomException
     */
    public function handle(ShopifyUser $shopifyUser)
    {
        $randomNumber = random_int(00, 99);

        $this->update($shopifyUser, [
            'name' => $shopifyUser->name . '-deleted-' . $randomNumber,
            'slug' => $shopifyUser->slug . '-deleted-' . $randomNumber,
            'email' => $shopifyUser->email . '-deleted-' . $randomNumber,
            'status' => false
        ]);

        if ($shopifyUser->customer) {
            DetachCustomerToPlatform::run($shopifyUser->customer, Platform::where('type', PlatformTypeEnum::SHOPIFY->value)->first());
        }

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

    public function inWebhook(ShopifyUser $shopifyUser, ActionRequest $request): void
    {
        $this->handle($shopifyUser);
    }
}

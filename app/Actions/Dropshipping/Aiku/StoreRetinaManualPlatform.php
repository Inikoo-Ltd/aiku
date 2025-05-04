<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Aiku;

use App\Actions\CRM\Customer\AttachCustomerToPlatform;
use App\Actions\Dropshipping\CustomerHasPlatforms\Hydrators\CustomerHasPlatformsHydratePortfolios;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\Platform;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaManualPlatform extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::MANUAL->value)->first();
        $customer = AttachCustomerToPlatform::make()->action($customer, $platform, []);

        $customerHasPlatform = CustomerHasPlatform::where('customer_id', $customer->customer_id)
        ->where('platform_id', $platform->platform_id)
        ->first();

        CustomerHasPlatformsHydratePortfolios::dispatch($customerHasPlatform);


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
        $customer = $request->user()->customer;
        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer);
    }
}

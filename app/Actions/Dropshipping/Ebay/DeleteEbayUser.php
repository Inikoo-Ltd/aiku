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
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\EbayUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteEbayUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(EbayUser $ebayUser): void
    {
        if ($ebayUser->customerSalesChannel) {
            UpdateCustomerSalesChannel::run($ebayUser->customerSalesChannel, [
                'status' => CustomerSalesChannelStatusEnum::CLOSED
            ]);
        }

        $ebayUser->forceDelete();
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

        $this->handle($customer->ebayUser);
    }
}

<?php

namespace App\Actions\Traits\Authorisations;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

trait WithCustomerSalesChannelEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if (property_exists($this, 'asAction') && $this->asAction) {
            return true;
        }

        $customerSalesChannel = $request->route('customerSalesChannel');
        if (!$customerSalesChannel instanceof CustomerSalesChannel) {
            return false;
        }

        return self::canEditCustomerSalesChannelsIn($request->user(), $customerSalesChannel->shop);
    }

    public static function canEditCustomerSalesChannelsIn(?User $user, Shop $shop): bool
    {
        if (!$user) {
            return false;
        }

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            return $user->authTo("fulfilment-shop.{$shop->fulfilment->id}.edit");
        }

        return $user->authTo("crm.$shop->id.edit");
    }
}

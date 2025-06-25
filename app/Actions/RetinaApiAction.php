<?php
/*
 * author Arya Permana - Kirin
 * created on 24-06-2025-17h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions;

use App\Actions\Traits\WithTab;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class RetinaApiAction
{
    use AsAction;
    use WithAttributes;
    use WithTab;


    protected Customer $customer;
    protected WebUser $webUser;
    protected ShopifyUser|TiktokUser|WebUser|WooCommerceUser|EbayUser|null $platformUser;
    protected Platform $platform;
    protected CustomerSalesChannel $customerSalesChannel;
    protected bool $asPupil = false;
    protected ?Fulfilment $fulfilment;
    protected ?FulfilmentCustomer $fulfilmentCustomer;
    protected Organisation $organisation;
    protected Shop $shop;
    protected bool $asAction = false;


    protected array $validatedData;

    public function initialisationFromDropshipping(ActionRequest $request): static
    {
        $this->customerSalesChannel = $request->user();
        $this->customer = $this->customerSalesChannel->customer;
        $this->shop = $this->customer->shop;
        $this->platformUser = $this->customerSalesChannel->user;
        $this->platform = $this->customerSalesChannel->platform;
        $this->organisation = $this->shop->organisation;
        $this->fillFromRequest($request);

        $this->validatedData = $this->validateAttributes();

        return $this;
    }


    public function authorize(ActionRequest $request): bool
    {

        if ($this->asAction) {
            return true;
        }

        if ($this->shop->type === ShopTypeEnum::FULFILMENT && $this->customerSalesChannel->customer->status === CustomerStatusEnum::APPROVED
            && $this->fulfilmentCustomer->rentalAgreement) {
            return true;
        }

        if ($this->shop->type === ShopTypeEnum::DROPSHIPPING && $this->customerSalesChannel->id === $request->user()->id) {
            return true;
        }

        if ($this->shop->type === ShopTypeEnum::B2B && $this->customerSalesChannel->id === $request->user()->id) {
            return true;
        }

        // Deny access if none of the above conditions pass.
        return false;
    }
}

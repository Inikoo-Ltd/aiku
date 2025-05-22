<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Feb 2024 20:59:47 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions;

use App\Actions\Traits\WithTab;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
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

class RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithTab;


    protected Website $website;
    protected Customer $customer;
    protected WebUser $webUser;
    protected ShopifyUser|TiktokUser|WebUser|WooCommerceUser|null $platformUser;
    protected Platform $platform;
    protected ShopifyUser $shopifyUser;
    protected bool $asPupil = false;
    protected ?Fulfilment $fulfilment;
    protected ?FulfilmentCustomer $fulfilmentCustomer;
    protected Organisation $organisation;
    protected Shop $shop;
    protected bool $asAction = false;


    protected array $validatedData;


    public function registerDropshippingInitialisation(Shop $shop, ActionRequest $request): static
    {
        $this->shop = $shop;
        $this->organisation = $this->shop->organisation;
        $this->website = $request->get('website');
        $this->fillFromRequest($request);
        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    public function registerFulfilmentInitialisation(Fulfilment $fulfilment, ActionRequest $request): static
    {
        $this->fulfilment = $fulfilment;
        $this->shop = $this->fulfilment->shop;
        $this->organisation = $this->shop->organisation;
        $this->website = $request->get('website');
        $this->fillFromRequest($request);
        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    public function initialisation(ActionRequest $request): static
    {
        $this->webUser = $request->user();
        $this->customer = $this->webUser->customer;
        $this->fulfilmentCustomer = $this->customer->fulfilmentCustomer;
        $this->shop = $this->customer->shop;
        $this->fulfilment = $this->shop->fulfilment;
        $this->organisation = $this->shop->organisation;
        $this->website = $request->get('website');
        $this->fillFromRequest($request);

        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    public function initialisationFulfilmentActions(FulfilmentCustomer $fulfilmentCustomer, array $modelData): static
    {
        $this->fulfilment = $fulfilmentCustomer->fulfilment;
        $this->fulfilmentCustomer = $fulfilmentCustomer;
        $this->customer = $fulfilmentCustomer->customer;
        $this->shop = $this->fulfilment->shop;
        $this->organisation = $this->fulfilment->organisation;
        $this->webUser = $this->customer->webUsers()->first();
        $this->setRawAttributes($modelData);
        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    public function initialisationActions(Customer $customer, array $modelData): static
    {
        $this->customer = $customer;
        $this->shop = $this->customer->shop;
        $this->organisation = $this->customer->organisation;
        $this->webUser = $this->customer->webUsers()->first();
        $this->setRawAttributes($modelData);
        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    public function logoutInitialisation(ActionRequest $request): static
    {

        $this->website = $request->get('website');

        $this->shop = $this->website->shop;
        $this->fulfilment = $this->shop->fulfilment;
        $this->organisation = $this->shop->organisation;

        $this->fillFromRequest($request);

        $this->validatedData = $this->validateAttributes();

        return $this;
    }




    public function initialisationFromPupil(ActionRequest $request): static
    {
        $this->asPupil = true;
        $this->shopifyUser = $request->user('pupil');
        $this->customer = $this->shopifyUser->customer;
        $this->fulfilmentCustomer = $this->customer->fulfilmentCustomer;
        $this->shop = $this->customer->shop;
        $this->fulfilment = $this->shop->fulfilment;
        $this->organisation = $this->shop->organisation;
        $this->website = $this->shop->website;
        $this->fillFromRequest($request);

        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    public function authorize(ActionRequest $request): bool
    {

        if ($this->asAction) {
            return true;
        }
        // Define the segments or route names that should always be accessible
        $publicRoutes = ['login', 'register', 'profile', 'logout', 'home', 'dashboard', 'password', 'reset-password', 'tiktok'];

        // Option 1: Check if the route's name is in the list.
        if ($request->route() && in_array($request->route()->getName(), $publicRoutes, true)) {
            return true;
        }

        // Option 2: Alternatively, check if the URL path contains any of these segments.
        foreach ($publicRoutes as $segment) {
            if (Str::contains($request->path(), $segment) || Str::contains($request->route()->getName(), $segment)) {
                return true;
            }
        }


        if ($this->shop->type === ShopTypeEnum::FULFILMENT && $this->webUser->customer->status === CustomerStatusEnum::APPROVED
            && $this->fulfilmentCustomer->rentalAgreement) {
            return true;
        }

        if ($this->shop->type === ShopTypeEnum::DROPSHIPPING && $this->webUser->id === $request->user()->id) {
            return true;
        }

        if ($this->shop->type === ShopTypeEnum::B2B && $this->webUser->id === $request->user()->id) {
            return true;
        }

        // Deny access if none of the above conditions pass.
        return false;
    }
}

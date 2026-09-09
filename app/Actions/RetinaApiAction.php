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
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\StoredItem;
use App\Models\Helpers\Media;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
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
    protected Group $group;
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
        $this->group = $this->shop->group;
        $this->assertBoundModelsAreOwned($request);
        $this->fillFromRequest($request);

        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    public function initialisationFromFulfilment(ActionRequest $request): static
    {
        $this->customerSalesChannel = $request->user();

        $this->customer = $this->customerSalesChannel->customer;
        $this->shop = $this->customer->shop;
        $this->platformUser = $this->customerSalesChannel->user;
        $this->platform = $this->customerSalesChannel->platform;
        $this->organisation = $this->shop->organisation;
        $this->group = $this->shop->group;
        $this->fulfilmentCustomer = $this->customerSalesChannel->customer->fulfilmentCustomer;
        $this->assertBoundModelsAreOwned($request);
        $this->fillFromRequest($request);

        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    /**
     * authorize() only establishes that the token is valid, never that the record named in the
     * URL belongs to the token's customer, so every route-bound model is checked here instead:
     * one place, rather than a check each new endpoint has to remember. A record belonging to
     * someone else is reported as missing, so the response cannot be used to discover which
     * ids exist. While app.enforce_api_ownership is off the refusal is recorded on the request
     * log rather than applied, so a client that depends on the old behaviour surfaces before it
     * breaks.
     */
    /**
     * Media carries no owner of its own, so it is reached through what it is attached to: the
     * catalogue of the customer's own shop, or their own stored items. Without this every file
     * on the platform, invoices and chat attachments included, is one sequential id away.
     */
    protected function mediaIsVisibleToCustomer(Media $media): bool
    {
        return DB::table('model_has_media')
            ->where('media_id', $media->id)
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('model_type', class_basename(Product::class))
                        ->whereIn('model_id', DB::table('products')->select('id')->where('shop_id', $this->shop->id));
                })->orWhere(function ($query) {
                    $query->where('model_type', class_basename(StoredItem::class))
                        ->whereIn('model_id', DB::table('stored_items')->select('id')->where('fulfilment_customer_id', $this->customer->fulfilmentCustomer?->id));
                });
            })
            ->exists();
    }

    protected function assertBoundModelsAreOwned(ActionRequest $request): void
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (!$parameter instanceof Model) {
                continue;
            }

            $isOwned = match (true) {
                $parameter instanceof Order,
                $parameter instanceof Portfolio,
                $parameter instanceof Transaction,
                $parameter instanceof CustomerClient => $parameter->customer_id === $this->customer->id,
                $parameter instanceof PalletReturn   => $parameter->fulfilment_customer_id === $this->customer->fulfilmentCustomer?->id,
                $parameter instanceof Product        => $parameter->shop_id === $this->shop->id,
                $parameter instanceof Media          => $this->mediaIsVisibleToCustomer($parameter),
                default                              => true,
            };

            if ($isOwned) {
                continue;
            }

            if (!config('app.enforce_api_ownership')) {
                // The logging middleware terminates on the original request, not this ActionRequest.
                $violations   = request()->attributes->get('retina_api_ownership_violations', []);
                $violations[] = class_basename($parameter).' '.$parameter->getKey().' is not owned by customer '.$this->customer->id;
                request()->attributes->set('retina_api_ownership_violations', $violations);

                continue;
            }

            abort(404);
        }
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

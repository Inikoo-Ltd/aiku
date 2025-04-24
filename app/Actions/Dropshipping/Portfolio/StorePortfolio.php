<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:35 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePortfolios;
use App\Actions\CRM\Customer\Hydrators\CustomerHydratePortfolios;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePortfolios;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePortfolios;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\StoredItem;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StorePortfolio extends OrgAction
{
    use WithNoStrictRules;

    private Customer $customer;

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData): Portfolio
    {
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'shop_id', $customer->shop_id);

        if (Arr::get($modelData, 'product_id')) {
            data_set($modelData, 'item_id', Arr::pull($modelData, 'product_id'));
            data_set($modelData, 'item_type', class_basename(Product::class));
        }

        if (Arr::get($modelData, 'stored_item_id')) {
            data_set($modelData, 'item_id', Arr::pull($modelData, 'stored_item_id'));
            data_set($modelData, 'item_type', class_basename(StoredItem::class));
        }

        $portfolio = DB::transaction(function () use ($customer, $modelData) {
            /** @var Portfolio $portfolio */
            $portfolio = $customer->portfolios()->create($modelData);
            $portfolio->stats()->create();

            return $portfolio;
        });


        GroupHydratePortfolios::dispatch($customer->group)->delay($this->hydratorsDelay);
        OrganisationHydratePortfolios::dispatch($customer->organisation)->delay($this->hydratorsDelay);
        ShopHydratePortfolios::dispatch($customer->shop)->delay($this->hydratorsDelay);
        CustomerHydratePortfolios::dispatch($customer)->delay($this->hydratorsDelay);

        return $portfolio;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'platform_id'    => ['required', Rule::exists('platforms', 'id')],
            'product_id'     => ['sometimes', 'required', Rule::exists('products', 'id')->where('shop_id', $this->shop->id)],
            'stored_item_id' => ['sometimes', 'required', Rule::exists('stored_items', 'id')],
            'reference'      => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                new IUnique(
                    table: 'portfolios',
                    extraConditions: [
                        ['column' => 'customer_id', 'value' => $this->customer->id],
                        ['column' => 'status', 'value' => true],
                    ]
                ),
            ],
            'type'           => ['sometimes', Rule::enum(PortfolioTypeEnum::class)],
            'status'         => 'sometimes|boolean',
            'last_added_at'  => 'sometimes|date',
        ];

        if (!$this->strict) {
            $rules['last_removed_at'] = ['sometimes', 'date'];
            $rules                    = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }


    /**
     * @throws \Throwable
     */
    public function action(Customer $customer, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Portfolio
    {
        if (!$audit) {
            Portfolio::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->customer       = $customer;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Customer $customer, ActionRequest $request): Portfolio
    {
        $this->customer = $customer;

        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.customers.show.portfolios.index', [$this->customer->organisation->slug, $this->customer->shop->slug, $this->customer->slug]);
    }

}

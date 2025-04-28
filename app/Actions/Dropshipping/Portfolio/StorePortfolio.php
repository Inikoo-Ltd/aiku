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
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\StoredItem;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
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
    public function handle(Customer $customer, Product|StoredItem $item, array $modelData): Portfolio
    {
        data_set($modelData, 'last_added_at', now(), overwrite: false);

        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'shop_id', $customer->shop_id);


        data_set($modelData, 'item_id', $item->id);
        data_set($modelData, 'item_type', class_basename($item));
        data_set($modelData, 'item_code', $item instanceof StoredItem ? $item->reference : $item->code);
        data_set($modelData, 'item_name', $item->name);


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
            'platform_id'   => ['required', Rule::exists('platforms', 'id')],
            'reference'     => [
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
            'status'        => 'sometimes|boolean',
            'last_added_at' => 'sometimes|date',
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
    public function action(Customer $customer, Product|StoredItem $item, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Portfolio
    {
        if (!$audit) {
            Portfolio::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->customer       = $customer;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $item, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Customer $customer, Product $product, ActionRequest $request): Portfolio
    {
        $this->customer = $customer;

        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $product, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.customers.show.portfolios.index', [$this->customer->organisation->slug, $this->customer->shop->slug, $this->customer->slug]);
    }

}

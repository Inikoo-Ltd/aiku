<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:35 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StorePortfolio extends OrgAction
{
    private Customer $customer;

    public function handle(Customer $customer, array $modelData): Portfolio
    {
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'shop_id', $customer->organisation_id);


        /** @var Portfolio $dropshippingCustomerPortfolio */
        $dropshippingCustomerPortfolio = $customer->portfolios()->create($modelData);
        $dropshippingCustomerPortfolio->stats()->create();


        return $dropshippingCustomerPortfolio;
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request)
    {
        $this->customer = $customer;

        $this->initialisationFromShop($shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->hasPermissionTo("crm.{$this->shop->id}.edit");
    }


    public function rules(): array
    {
        return [
            'product_id'      => ['required', Rule::Exists('products', 'id')->where('shop_id', $this->shop->id)],
            'reference'       => [
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
            'type'            => ['sometimes', Rule::enum(PortfolioTypeEnum::class)],
            'status'          => 'sometimes|boolean',
            'created_at'      => 'sometimes|date',
            'last_added_at'   => 'sometimes|date',
            'last_removed_at' => 'sometimes|date',
            'source_id'       => 'sometimes|string|max:255',
        ];
    }


    public function action(Customer $customer, array $modelData): Portfolio
    {
        $this->asAction = true;
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.customers.show.portfolios.index', [$this->customer->organisation->slug, $this->customer->shop->slug, $this->customer->slug]);
    }

}

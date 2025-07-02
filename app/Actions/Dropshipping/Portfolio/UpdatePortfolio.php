<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:21 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePortfolios;
use App\Actions\CRM\Customer\Hydrators\CustomerHydratePortfolios;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePortfolios;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePortfolios;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdatePortfolio extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    private Portfolio $portfolio;

    public function handle(Portfolio $portfolio, array $modelData): Portfolio
    {
        if (Arr::exists($modelData, 'customer_product_name') && !Arr::exists($modelData, 'platform_handle')) {
            data_set(
                $modelData,
                'platform_handle',
                Str::slug(Arr::get($modelData, 'customer_product_name'))
            );
        }

        if (Arr::exists($modelData, 'customer_price')) {
            $price = $portfolio->item->price ?? 0;

            data_set($modelData, 'selling_price', Arr::get($modelData, 'customer_price'));
            data_set($modelData, 'margin', CalculationsProfitMargin::run(Arr::get($modelData, 'selling_price'), $price));
        }

        $portfolio = $this->update($portfolio, $modelData, ['data']);

        if ($portfolio->wasChanged(['status'])) {
            GroupHydratePortfolios::dispatch($portfolio->group)->delay($this->hydratorsDelay);
            OrganisationHydratePortfolios::dispatch($portfolio->organisation)->delay($this->hydratorsDelay);
            ShopHydratePortfolios::dispatch($portfolio->shop)->delay($this->hydratorsDelay);
            CustomerHydratePortfolios::dispatch($portfolio->customer)->delay($this->hydratorsDelay);
        }


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
            'reference'             => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                new IUnique(
                    table: 'portfolios',
                    extraConditions: [
                        ['column' => 'customer_id', 'value' => $this->shop->id],
                        ['column' => 'status', 'value' => true],
                        ['column' => 'id', 'value' => $this->portfolio->id, 'operator' => '!='],
                    ]
                ),
            ],
            'selling_price'         => ['sometimes', 'numeric', 'min:0'],
            'status'                => ['sometimes', 'boolean'],
            'last_added_at'         => 'sometimes|date',
            'last_removed_at'       => 'sometimes|date',
            'item_id'               => 'sometimes|integer',
            'item_type'             => 'sometimes|string',
            'item_name'             => 'sometimes|string',
            'item_code'             => 'sometimes|string',
            'customer_product_name' => 'sometimes|string',
            'customer_price'        => ['sometimes', 'numeric', 'min:0'],
            'customer_description'  => ['sometimes', 'string', 'nullable'],
            'platform_product_id'   => 'sometimes|string',
            'platform_handle'       => 'sometimes|string',
            'errors_response'       => 'sometimes|array'
        ];

        if (!$this->strict) {
            $rules            = $this->noStrictUpdateRules($rules);
            $rules['shop_id'] = ['sometimes', 'required', Rule::exists('shops', 'id')];
        }

        return $rules;
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): Portfolio
    {
        $this->initialisationFromShop($portfolio->shop, $request);

        return $this->handle($portfolio, $this->validatedData);
    }

    public function action(Portfolio $portfolio, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Portfolio
    {
        $this->strict = $strict;
        if (!$audit) {
            Portfolio::disableAuditing();
        }
        $this->asAction       = true;
        $this->portfolio      = $portfolio;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($portfolio->shop, $modelData);

        return $this->handle($portfolio, $this->validatedData);
    }


}

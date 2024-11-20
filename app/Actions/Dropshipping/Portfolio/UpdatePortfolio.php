<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:21 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\Hydrators\CustomerHydratePortfolios;
use App\Actions\Dropshipping\Portfolio\Hydrators\GroupHydratePortfolios;
use App\Actions\Dropshipping\Portfolio\Hydrators\HydratePortfolio;
use App\Actions\Dropshipping\Portfolio\Hydrators\OrganisationHydratePortfolios;
use App\Actions\Dropshipping\Portfolio\Hydrators\ShopHydratePortfolios;
use App\Actions\Dropshipping\Portfolio\Search\PortfolioRecordSearch;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdatePortfolio extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    private Portfolio $portfolio;

    public function handle(Portfolio $portfolio, array $modelData): Portfolio
    {
        $portfolio = $this->update($portfolio, $modelData, ['data']);

        PortfolioRecordSearch::dispatch($portfolio);
        if ($portfolio->wasChanged(['status'])) {
            // todo #1115 put here the hydrators
            HydratePortfolio::run($portfolio);
            GroupHydratePortfolios::run($portfolio->group);
            OrganisationHydratePortfolios::run($portfolio->organisation);
            ShopHydratePortfolios::run($portfolio->shop);
            CustomerHydratePortfolios::make()->handle($portfolio->customer);
        }


        return $portfolio;
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
        $rules = [
            'reference' => ['sometimes', 'nullable','string', 'max:255',
                            new IUnique(
                                table: 'portfolios',
                                extraConditions: [
                                    ['column' => 'customer_id', 'value' => $this->shop->id],
                                    ['column' => 'status', 'value' => true],
                                    ['column' => 'id', 'value' => $this->portfolio->id, 'operator' => '!='],
                                ]
                            ),
                ],
            'status'          => 'sometimes|boolean',
            'last_added_at'   => 'sometimes|date',
            'last_removed_at' => 'sometimes|date',
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }



    public function action(Portfolio $portfolio, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Portfolio
    {
        $this->strict = $strict;
        if (!$audit) {
            Portfolio::disableAuditing();
        }
        $this->asAction                      = true;
        $this->portfolio = $portfolio;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($portfolio->shop, $modelData);

        return $this->handle($portfolio, $this->validatedData);
    }


}

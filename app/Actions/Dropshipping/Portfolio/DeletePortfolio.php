<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:21 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Shopify\Product\DeleteShopifyUserHasProduct;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;

class DeletePortfolio extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    private Portfolio $portfolio;

    public function handle(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio): void
    {
        match ($customerSalesChannel->platform->type) {
            PlatformTypeEnum::SHOPIFY => DeleteShopifyUserHasProduct::run($portfolio->shopifyPortfolio),
            default   => null
        };

        $portfolio->stats()->delete();
        $portfolio->delete();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisationFromShop($portfolio->shop, $request);

        $this->handle($customerSalesChannel, $portfolio);
    }

    public function action(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): void
    {
        $this->strict = $strict;
        if (!$audit) {
            Portfolio::disableAuditing();
        }
        $this->asAction       = true;
        $this->portfolio      = $portfolio;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($portfolio->shop, $modelData);

        $this->handle($customerSalesChannel, $portfolio);
    }
}

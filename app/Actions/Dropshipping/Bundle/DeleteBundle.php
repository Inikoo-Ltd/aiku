<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:35 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Bundle;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateBundles;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Portfolio\UnlinkRetinaPortfolio;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Bundle;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class DeleteBundle extends OrgAction
{
    use WithNoStrictRules;
    use WithActionUpdate;

    private Customer $customer;

    public function handle(Bundle $bundle): void
    {
        /** @var Product $product */
        $product = $bundle->bundleable;

        // TODO: Delete bundle in each sales channel

        UpdateProduct::run($bundle->bundleable, [
            'state' => ProductStateEnum::DISCONTINUED,
            'status' => ProductStatusEnum::DISCONTINUED
        ]);

        $this->update($bundle, [
            'status' => false,
            'platform_status' => false,
            'has_valid_platform_product_id' => false,
            'exist_in_platform' => false
        ]);

        foreach ($product->portfolios as $portfolio) {
            UnlinkRetinaPortfolio::run($portfolio);
        }

        ShopHydrateBundles::dispatch($bundle->customer->shop)->delay($this->hydratorsDelay);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function asController(Bundle $bundle, ActionRequest $request): void
    {
        $this->initialisationFromShop($this->customer->shop, $request);

        $this->handle($bundle);
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Jun 2024 09:33:19 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateAssets;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProducts;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateAssets;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProducts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateAssets;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateProducts;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class DeleteProduct extends OrgAction
{
    use WithActionUpdate;

    public string $commandSignature = 'delete:product {id}';

    public function handle(Product $product, array $deletedData = [], bool $skipHydrate = false): Product
    {
        $product->delete();
        $product->asset->delete();
        $product->productVariants()->delete();
        $product = $this->update($product, $deletedData, ['data']);
        if (!$skipHydrate) {
            ShopHydrateProducts::dispatch($product->shop)->delay(2);
        }

        GroupHydrateProducts::dispatch($product->group)->delay(2);
        GroupHydrateAssets::dispatch($product->group)->delay(2);
        OrganisationHydrateProducts::dispatch($product->organisation)->delay(2);
        OrganisationHydrateAssets::dispatch($product->organisation)->delay(2);
        ShopHydrateProducts::dispatch($product->shop)->delay(2);
        ShopHydrateAssets::dispatch($product->shop)->delay(2);
        return $product;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }

    public function asController(Product $product, ActionRequest $request): Product
    {
        $this->initialisationFromShop($product->shop, $request);

        return $this->handle($product, $this->validatedData);
    }


    public function asCommand(Command $command): int
    {
        try {
            /** @var Product $product */
            $product = Product::findOrFail($command->argument('id'));
        } catch (Exception) {
            $command->error('Product not found');

            return 1;
        }
        $this->handle($product);

        return 0;
    }


}

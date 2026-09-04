<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026 09:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Sentry;

class StoreFaireProduct extends OrgAction
{
    use WithFaireProductPayload;

    public string $jobQueue = 'hydrators-slave';
    public int $jobTries = 1;

    public string $commandSignature = 'faire:store_product {product}';

    public function handle(Product $product): void
    {
        if (!$this->isFaireSyncableProduct($product) || $product->marketplace_id) {
            return;
        }

        $response = $product->shop->createFaireProduct($this->getCreateFaireProductPayload($product));

        $faireProductId = Arr::get($response, 'id');
        $faireVariant   = Arr::get($response, 'variants.0', []);
        $faireVariantId = Arr::get($faireVariant, 'id');

        if (!$faireProductId || !$faireVariantId) {
            $this->reportFaireFailure($product, $response);

            return;
        }

        $product->updateQuietly([
            'marketplace_id'        => $faireVariantId,
            'marketplace_second_id' => $faireProductId,
            'data'                  => [
                ...($product->data ?? []),
                'faire' => $faireVariant
            ]
        ]);

        UpdateFaireProductInventoryQuantity::dispatch($product->refresh());
    }

    protected function reportFaireFailure(Product $product, array $response): void
    {
        Sentry::captureMessage(
            'Faire product creation failed for product '.$product->slug.': '.json_encode(Arr::get($response, 'error', $response))
        );
    }

    public function asCommand(Command $command): int
    {
        $product = Product::where('slug', $command->argument('product'))->firstOrFail();

        $this->handle($product);

        $product->refresh();
        if (!$product->marketplace_id) {
            $command->error("Product $product->slug was not created in Faire");

            return 1;
        }

        $command->info("Product $product->slug created in Faire as $product->marketplace_second_id");

        return 0;
    }
}

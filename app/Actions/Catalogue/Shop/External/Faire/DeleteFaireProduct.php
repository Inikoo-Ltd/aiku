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

class DeleteFaireProduct extends OrgAction
{
    use WithFaireProductPayload;

    public string $jobQueue = 'hydrators-slave';
    public int $jobTries = 1;

    public string $commandSignature = 'faire:delete_product {product}';

    public function handle(Product $product, ?string $faireProductId = null, ?string $faireVariantId = null): void
    {
        $faireProductId ??= $product->marketplace_second_id;
        $faireVariantId ??= $product->marketplace_id;

        if (!$this->isFaireSyncableProduct($product) || !$faireProductId) {
            return;
        }

        $shop = $product->shop;

        if ($faireVariantId && $this->hasSiblingsInFaireProduct($product, $faireProductId)) {
            $response = $shop->deleteFaireProductVariant($faireProductId, $faireVariantId);
        } else {
            $response = $shop->deleteFaireProduct($faireProductId);
        }

        if (Arr::has($response, 'error')) {
            Sentry::captureMessage(
                'Faire product deletion failed for product '.$product->slug.': '.json_encode(Arr::get($response, 'error'))
            );
        }
    }

    public function asCommand(Command $command): int
    {
        $product = Product::withTrashed()->where('slug', $command->argument('product'))->firstOrFail();

        $command->info("Deleting $product->slug from Faire");
        $this->handle($product);

        return 0;
    }
}

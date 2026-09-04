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

class UpdateFaireProduct extends OrgAction
{
    use WithFaireProductPayload;

    public string $jobQueue = 'hydrators-slave';
    public int $jobTries = 1;

    public string $commandSignature = 'faire:update_product {product}';

    /**
     * The product columns that Faire mirrors, an update of any other column is not pushed.
     */
    public const SYNCED_FIELDS = [
        'name',
        'code',
        'description',
        'description_title',
        'price',
        'rrp',
        'units',
        'barcode',
        'state',
        'is_for_sale',
    ];

    public function handle(Product $product): void
    {
        if (!$this->isFaireSyncableProduct($product) || !$product->marketplace_second_id) {
            return;
        }

        $shop = $product->shop;

        if ($product->marketplace_id && $this->hasSiblingsInFaireProduct($product, $product->marketplace_second_id)) {
            $response = $shop->updateFaireProductVariant(
                $product->marketplace_second_id,
                $product->marketplace_id,
                $this->getFaireVariantPayload($product)
            );
        } else {
            $response = $shop->updateFaireProduct(
                $product->marketplace_second_id,
                $this->getUpdateFaireProductPayload($product)
            );
        }

        if (!Arr::get($response, 'id')) {
            Sentry::captureMessage(
                'Faire product update failed for product '.$product->slug.': '.json_encode(Arr::get($response, 'error', $response))
            );
        }
    }

    public function asCommand(Command $command): int
    {
        $product = Product::where('slug', $command->argument('product'))->firstOrFail();

        $command->info("Updating $product->slug in Faire");
        $this->handle($product);

        return 0;
    }
}

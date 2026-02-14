<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Dec 2025 21:03:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Product;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\WithAuroraParsers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductIsForSaleFromAurora
{
    use AsAction;
    use WithOrganisationSource;
    use WithAuroraParsers;


    public function getCommandSignature(): string
    {
        return 'maintenance:update_product_is_for_sale {organisation}';
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $organisation       = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);


        $auroraProducts = DB::connection('aurora')->table('Product Dimension')->select(['Product ID', 'Product Code', 'Product Web Configuration'])->get();

        $progressBar = $command->getOutput()->createProgressBar(count($auroraProducts));
        $progressBar->setFormat('debug');
        $progressBar->start();

        foreach ($auroraProducts as $auroraProduct) {
            /** @var Product $product */
            $product = Product::where('source_id', $organisation->id.':'.$auroraProduct->{'Product ID'})->first();
            if (!$product) {
                continue;
            }

            if ($product->shop->state == ShopStateEnum::CLOSED) {
                continue;
            }

            if (!$product->variant_id) {
                if ($auroraProduct->{'Product Web Configuration'} == 'Offline') {
                    if ($product->is_for_sale) {
                        $command->info($product->slug.' will be set as NOT FOR SALE');
                        UpdateProduct::make()->action($product, [
                            'is_for_sale' => false
                        ]);
                    }
                } elseif (!$product->is_for_sale && $product->is_main) {
                    $command->info($product->slug.' will be set as FOR SALE *********');
                    UpdateProduct::make()->action($product, [
                        'is_for_sale' => true
                    ]);
                }
            } elseif ($auroraProduct->{'Product Web Configuration'} == 'Offline' && $product->is_for_sale) {
                $command->info($product->slug.'NEW VARIANT  will be set as NOT FOR SALE *********');
                $product->update(['is_for_sale' => true]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        $command->newLine();

        return 0;
    }

}

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
use App\Models\Catalogue\Product;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\WithAuroraParsers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductIsMainFromAurora
{
    use AsAction;
    use WithOrganisationSource;
    use WithAuroraParsers;


    public function getCommandSignature(): string
    {
        return 'maintenance:update_product_is_main {organisation}';
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


        $auroraProducts = DB::connection('aurora')->table('Product Dimension')->select(['Product ID', 'Product Code', 'is_variant', 'Product Show Variant'])
            ->where('is_variant', 'Yes')->get();

        $progressBar = $command->getOutput()->createProgressBar(count($auroraProducts));
        $progressBar->setFormat('debug');
        $progressBar->start();

        foreach ($auroraProducts as $auroraProduct) {
            $products = DB::table('products')
                ->whereRaw('LOWER(code) = ?', [strtolower($auroraProduct->{'Product Code'})])->get();
            foreach ($products as $productData) {
                $product = Product::find($productData->id);
                if ($product) {
                    UpdateProduct::make()->action($product, [
                        'is_main' => false,
                        'is_for_sale' => false,
                    ]);
                }
            }
            $progressBar->advance();
        }

        $progressBar->finish();

        $command->newLine();

        return 0;
    }

}

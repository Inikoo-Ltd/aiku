<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Dec 2025 21:03:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Masters\MasterAsset;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\WithAuroraParsers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductTaxCategoryFromAurora
{
    use AsAction;
    use WithOrganisationSource;
    use WithAuroraParsers;


    public function getCommandSignature(): string
    {
        return 'maintenance:update_product_tax_category_from_aurora {organisation}';
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


        $auroraProducts = DB::connection('aurora')->table('Product Dimension')->select(['Product ID', 'Product Code', 'Product Tax Category Key', 'Product Tax Category Data'])->where('Product Tax Category Data', '!=', '{}')->get();
        foreach ($auroraProducts as $auroraProduct) {
            $auTaxData = json_decode($auroraProduct->{'Product Tax Category Data'}, true);
            $taxData   = [];
            foreach ($auTaxData as $auKey => $auValue) {
                $key               = $this->parseTaxCategory($auKey);
                $value             = $this->parseTaxCategory($auValue);
                $taxData[$key->id] = $value->id;
            }


            $masterAsset = MasterAsset::whereRaw('LOWER(code) = ?', [strtolower($auroraProduct->{'Product Code'})])->first();
            if ($masterAsset) {
                $taxCategoryData = $masterAsset->tax_category;

                $taxCategoryData = $taxCategoryData + $taxData;


                UpdateMasterAsset::make()->action(
                    $masterAsset,
                    [
                        'tax_category' => $taxCategoryData
                    ]
                );
                $command->line("Updated $masterAsset->slug with tax category data");


            }
        }


        return 0;
    }

}

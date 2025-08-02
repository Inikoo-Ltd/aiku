<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Aug 2025 09:51:10 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\OrgAction;
use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Traits\WithOrganisationSourceShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SetMasterProductCategoriesTranslationsFromAurora extends OrgAction
{
    use AsAction;
    use WithOrganisationSource;
    use WithOrganisationSourceShop;


    /**
     * @throws \Exception
     */
    public function handle(MasterProductCategory $masterProductCategory): MasterProductCategory
    {
        $code = $masterProductCategory->code;

        $translationName = [];
        foreach ($this->getOrganisationSourceShop() as $lang => $organisationShopIds) {
            $translationName[$lang] = null;

            foreach ($organisationShopIds as $organisationId => $shopId) {
                $auroraProduct = DB::connection('aurora_'.$organisationId)
                    ->table('Category Dimension')
                    ->leftJoin('Product Category Dimension', 'Category Key', 'Product Category Key')
                    ->select(['Category Label', 'Category Code'])
                    ->where('Product Category Store Key', $shopId)
                    ->whereRaw('LOWER(`Category Code`) = LOWER(?)', [$code])
                    ->first();
                if ($auroraProduct && $auroraProduct->{'Category Label'} != $auroraProduct->{'Category Code'} && $auroraProduct->{'Category Label'} != null && $auroraProduct->{'Category Label'} != '') {
                    $translationName[$lang] = $auroraProduct->{'Category Label'};
                    break;
                }
            }
        }


        $masterProductCategory->setTranslations('name_i8n', $translationName);
        $masterProductCategory->save();

        return $masterProductCategory;
    }


    public function getCommandSignature(): string
    {
        return 'set:master_assets_translations_from_aurora';
    }

    public function asCommand(Command $command): int
    {
        $command->info('Setting master assets translations from Aurora');

        $chunkSize = 100;


        $count = MasterProductCategory::whereIn(
            'type',
            [
                MasterProductCategoryTypeEnum::DEPARTMENT,
                MasterProductCategoryTypeEnum::FAMILY,

            ]
        )->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->start();

        MasterProductCategory::whereIn(
            'type',
            [
                MasterProductCategoryTypeEnum::DEPARTMENT,
                MasterProductCategoryTypeEnum::FAMILY,

            ]
        )
            ->chunk(
                $chunkSize,
                function ($masterProductCategories) use (&$count, $bar, $command) {
                    foreach ($masterProductCategories as $masterProductCategory) {
                        try {
                            $this->handle($masterProductCategory);
                        } catch (Exception $e) {
                            $command->error($e->getMessage());
                        }
                        $count++;
                        $bar->advance();
                    }
                }
            );

        $bar->finish();
        $command->newLine();
        $command->info("$count master assets processed");

        return 0;
    }
}

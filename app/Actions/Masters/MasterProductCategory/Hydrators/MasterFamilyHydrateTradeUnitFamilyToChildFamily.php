<?php

/*
 * author Louis Perez
 * created on 05-06-2026-15h-32m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\GrpAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Goods\TradeUnitFamily;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;

class MasterFamilyHydrateTradeUnitFamilyToChildFamily extends GrpAction
{
    public function handle(MasterProductCategory $masterFamily): void
    {
        foreach ($masterFamily->productCategories as $family) {
            UpdateProductCategory::make()->action($family, [
                'trade_unit_family_id' => $masterFamily->trade_unit_family_id,
            ]);
        }
    }

    public function action(MasterProductCategory $masterFamily): void
    {
        $this->initialisation($masterFamily->group, []);
        $this->handle($masterFamily);
    }

    public string $commandSignature = "hydrate:family_generate_trade_unit_family_id {masterProductCategory?}";

    public function asCommand(Command $command)
    {
        $masterFamilySlug = $command->argument('masterProductCategory');

        $query = MasterProductCategory::where('type', MasterProductCategoryTypeEnum::FAMILY)
            ->when(
                $masterFamilySlug,
                fn ($q) => $q->where('slug', $masterFamilySlug)
            );

        $tradeUnitFamily = TradeUnitFamily::all()->pluck('id', 'code');

        $command->info('Hydrating MasterFamily trade_unit_family_id and linkingto child');
        $command->newLine();
        $bar = $command->getOutput()->createProgressBar($query->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        $query
            ->orderBy('id')
            ->chunkById(500, function ($chunks) use ($tradeUnitFamily, &$bar) {
                foreach ($chunks as $masterFamily) {
                    $bar->advance();
                    $relatedTradeUnitFamilyId = data_get($tradeUnitFamily, $masterFamily->code, null);

                    if (!$relatedTradeUnitFamilyId) {
                        continue;
                    }

                    $masterFamily->update([
                        'trade_unit_family_id' => $relatedTradeUnitFamilyId
                    ]);
                    $this->handle($masterFamily);
                }
            });

        $bar->finish();
        $command->newLine();
        $command->info('Done.');

    }
}

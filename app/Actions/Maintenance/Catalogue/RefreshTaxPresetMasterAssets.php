<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Traits\WithLineTaxCategories;
use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Re-expands every master that follows a tax preset, so a rate change (a new UK reduced rate,
 * Spain renumbering a category) is applied to all food products in one command instead of a
 * per-product edit. Masters with a custom map (tax_preset null) are never touched.
 */
class RefreshTaxPresetMasterAssets
{
    use AsAction;
    use WithLineTaxCategories;

    public function getCommandSignature(): string
    {
        return 'maintenance:refresh_tax_preset_master_assets {preset?} {--apply : write the changes, otherwise only report}';
    }

    public function asCommand(Command $command): int
    {
        $presetNames = $command->argument('preset') ? [$command->argument('preset')] : array_keys($this->getTaxPresets());
        $apply       = (bool)$command->option('apply');

        foreach ($presetNames as $presetName) {
            $expanded = $this->expandTaxPreset($presetName);
            ksort($expanded);

            $masterAssets = MasterAsset::where('tax_preset', $presetName)->get();
            $stale        = $masterAssets->filter(function (MasterAsset $masterAsset) use ($expanded) {
                $current = array_map('intval', $masterAsset->tax_category ?? []);
                ksort($current);

                return $current != $expanded;
            });

            $command->line(sprintf(
                '%-12s expands to %s: %d masters follow it, %d stale',
                $presetName,
                json_encode($expanded),
                $masterAssets->count(),
                $stale->count()
            ));

            foreach ($stale as $masterAsset) {
                $command->line('  '.$masterAsset->code.'  '.json_encode($masterAsset->tax_category).' -> '.json_encode($expanded));

                if ($apply) {
                    UpdateMasterAsset::make()->action($masterAsset, ['tax_category' => $expanded]);
                }
            }
        }

        if (!$apply) {
            $command->info('Nothing written. Re-run with --apply to save.');
        }

        return 0;
    }
}

<?php

/*
 * author Louis Perez
 * created on 23-01-2026-14h-50m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Masters\MasterVariant\UpdateMasterVariant;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterVariant;
use Illuminate\Console\Command;

class RepairVariantProductLink
{
    use WithActionUpdate;

    public string $commandSignature = 'variant:fix_product_link';

    public function handle(MasterVariant $masterVariant)
    {
        UpdateMasterVariant::run($masterVariant, [
            'leader_id' => $masterVariant->leader_id,
            'number_minions'    => $masterVariant->number_minions,
            'number_dimensions' => $masterVariant->number_dimensions,
            'number_used_slots' => $masterVariant->number_used_slots,
            'number_used_slots_for_sale'    => $masterVariant->number_used_slots_for_sale,
            'data'  => $masterVariant->data,
        ]);
    }

    public function asCommand(Command $command): void
    {
        $command->info('Repairing Variant wrong Product Link');

        $query = MasterVariant::query();

        $total = (clone $query)->count();

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->start();

        $query->chunk(200, function ($masterVariants) use ($bar) {
            foreach ($masterVariants as $masterVariant) {
                $this->handle($masterVariant);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
    }

}

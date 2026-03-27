<?php

/*
 * author Louis Perez
 * created on 09-03-2026-09h-53m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters;

use App\Actions\Traits\WithActionUpdate;
use Artisan;
use Illuminate\Console\Command;

class HydrateMasterAssetsAndFamilyMismatch
{
    use WithActionUpdate;

    protected function handle(): void
    {
        Artisan::call('master_asset:hydrate_mismatch');
        Artisan::call('master_product_categories:hydrate_mismatch');

    }

    public string $commandSignature = 'hydrate:mismatch_detected';

    public function asCommand(Command $command): void
    {
        $command->info('Mismatch detected. Updating master assets and product categories.');
        $this->handle();
    }

}

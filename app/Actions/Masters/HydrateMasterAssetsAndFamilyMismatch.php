<?php

/*
 * author Louis Perez
 * created on 09-03-2026-09h-53m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters;

use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMismatch;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamiliesHydrateMismatch;
use App\Actions\Traits\WithActionUpdate;
use Illuminate\Console\Command;

class HydrateMasterAssetsAndFamilyMismatch
{
    use WithActionUpdate;

    protected function handle(Command $command): void
    {
        echo "Running master asset hydrate mismatch";
        MasterAssetHydrateMismatch::run();
        echo "== Done hydrating master assets mismatch ==";

        echo "Running master product categories hydrate mismatch";
        MasterFamiliesHydrateMismatch::run();
        echo "== Done hydrating master product categories mismatch ==";

    }

    public string $commandSignature = 'hydrate:mismatch_detected';

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }

}

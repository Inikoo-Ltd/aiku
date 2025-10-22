<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Oct 2025 10:41:26 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DeleteMasterAsset extends OrgAction
{
    use WithActionUpdate;
    use WithMastersEditAuthorisation;


    public function handle(MasterAsset $masterAsset, bool $forceDelete=true): MasterAsset
    {
        if($masterAsset->stats){
            $masterAsset->stats->delete();
        }

        if($masterAsset->salesIntervals){
            $masterAsset->salesIntervals->delete();
        }

        DB::table('assets')->where('master_asset_id', $masterAsset->id)->delete();
        DB::table('products')->where('master_product_id', $masterAsset->id)->delete();
        if($forceDelete){
            $masterAsset->forceDelete();
        }else{
            $masterAsset->delete();
        }

        return $masterAsset;
    }


    public function asController(MasterAsset $masterAsset, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterAsset->group, $request);

        return $this->handle($masterAsset);
    }


    public function getCommandSignature(): string
    {
        return 'master_assets:delete {master_asset}';
    }


    public function asCommand(Command $command): int
    {
        $masterAsset = MasterAsset::withTrashed()->where('slug', $command->argument('master_asset'))->firstOrFail();
        $this->handle($masterAsset);
        $command->info('Deleted '.$masterAsset->name);

        return 0;
    }

}

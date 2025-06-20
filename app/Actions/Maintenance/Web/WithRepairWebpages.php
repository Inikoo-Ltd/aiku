<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 10:10:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Web\Webpage\WithStoreWebpage;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;

trait WithRepairWebpages
{
    use WithStoreWebpage;

    protected function getWebpageBlocksByType(Webpage $webpage, string $type): \Illuminate\Support\Collection
    {
        return DB::table('model_has_web_blocks')
            ->select(['web_blocks.layout', 'web_blocks.id', 'web_block_types.code as type','model_has_web_blocks.id as model_has_web_blocks_id'])
            ->leftJoin('web_blocks', 'web_blocks.id', '=', 'model_has_web_blocks.web_block_id')
            ->leftJoin('web_block_types', 'web_block_types.id', '=', 'web_blocks.web_block_type_id')
            ->where('web_block_types.code', $type)
            ->where('model_has_web_blocks.model_type', 'Webpage')
            ->where('model_has_web_blocks.model_id', $webpage->id)->get();
    }

}

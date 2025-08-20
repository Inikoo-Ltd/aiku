<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Jul 2023 15:42:45 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlockHistory;

use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Web\WebBlockHistory;
use Exception;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreWebBlockHistory
{
    use AsAction;

    /**
     * @throws Exception
     */
    public function handle(ModelHasWebBlocks $modelHasWebBlocks, array $modelData): WebBlockHistory
    {
        $webpage = $modelHasWebBlocks->webpage;
        /** @var WebBlockHistory $webBlockHistory */
        data_set($modelData, 'group_id', $modelHasWebBlocks->group_id);
        data_set($modelData, 'website_id', $modelHasWebBlocks->website_id);
        data_set($modelData, 'web_block_id', $modelHasWebBlocks->web_block_id);
        data_set($modelData, 'web_block_type_id', $modelHasWebBlocks->webBlock->web_block_type_id);
        data_set($modelData, 'checksum', md5(json_encode(Arr::get($modelData, 'layout', []))));
        return $webpage->webBlockHistories()->create($modelData);
    }

    public function rules(): array
    {
        return [
            'layout'     => ['sometimes', 'array'],
        ];

    }

    public function action(ModelHasWebBlocks $modelHasWebBlocks, array $modelData): WebBlockHistory
    {
        return $this->handle($modelHasWebBlocks, $modelData);
    }
}

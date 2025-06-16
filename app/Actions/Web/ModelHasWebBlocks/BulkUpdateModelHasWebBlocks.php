<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 12:59:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\ModelHasWebBlocks;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ModelHasWebBlocks;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class BulkUpdateModelHasWebBlocks extends OrgAction
{
    use WithWebAuthorisation;
    use WithActionUpdate;


    public function handle(array $modelData): void
    {
        foreach (Arr::get($modelData, 'web_blocks') as $modelHasWebBlock) {
            $modelHasWebBlock = UpdateModelHasWebBlocks::make()->action(ModelHasWebBlocks::find(Arr::get($modelHasWebBlock, 'id')), Arr::except($modelHasWebBlock, 'id'));

            $modelHasWebBlock->webBlock->refresh();
        }
    }

    public function asController(ActionRequest $request): void
    {
        $this->handle($request->all());
    }

    public function rules(): array
    {
        return [
            'web_blocks' => ['required', 'array']
        ];
    }
}

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
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Events\BroadcastPreviewHeaderFooter;
use App\Events\BroadcastPreviewWebpage;
use App\Http\Resources\Web\WebpageResource;
use App\Models\Dropshipping\ModelHasWebBlocks;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateModelHasWebBlocks extends OrgAction
{
    use WithWebAuthorisation;
    use WithActionUpdate;


    public function handle(ModelHasWebBlocks $modelHasWebBlocks, array $modelData): ModelHasWebBlocks
    {
        $this->update($modelHasWebBlocks, Arr::only($modelData, ['show', 'show_logged_in', 'show_logged_out']));
        $this->update($modelHasWebBlocks->webBlock, Arr::only($modelData, ['layout']));
        $modelHasWebBlocks->refresh();
        UpdateWebpageContent::run($modelHasWebBlocks->webpage);

        /* BroadcastPreviewWebpage::dispatch($modelHasWebBlocks->webpage);
        BroadcastPreviewHeaderFooter::dispatch($modelHasWebBlocks->website); */

        return $modelHasWebBlocks;

    }

    public function asController(ModelHasWebBlocks $modelHasWebBlocks, ActionRequest $request): ModelHasWebBlocks
    {
        return $this->handle($modelHasWebBlocks, $request->all());
    }

    public function rules(): array
    {
        return [
            'layout'            => ['sometimes', 'array'],
            'show'              => ['sometimes', 'boolean'],
            'show_logged_in'    => ['sometimes', 'boolean'],
            'show_logged_out'   => ['sometimes', 'boolean'],
        ];
    }


    public function action(ModelHasWebBlocks $modelHasWebBlocks, $modelData): ModelHasWebBlocks
    {
        $this->asAction = true;

        $this->initialisation($modelHasWebBlocks->organisation, $modelData);

        return $this->handle($modelHasWebBlocks, $this->validatedData);
    }

    public function jsonResponse(ModelHasWebBlocks $modelHasWebBlocks): WebpageResource
    {
        $modelHasWebBlocks->webpage->refresh();
        return new WebpageResource($modelHasWebBlocks->webpage);
    }

}

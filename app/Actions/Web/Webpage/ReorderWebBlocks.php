<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 13:19:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Http\Resources\Web\WebpageResource;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class ReorderWebBlocks extends OrgAction
{
    use WithWebEditAuthorisation;

    protected Shop $shop;

    public function handle(Webpage $webpage, array $modelData): Webpage
    {
        $webpage->webBlocks()->syncWithoutDetaching(Arr::get($modelData, 'positions', []));
        UpdateWebpageContent::run($webpage->refresh());

        return $webpage;
    }

    public function jsonResponse(Webpage $webpage): WebpageResource
    {
        return WebpageResource::make($webpage);
    }

    public function rules(): array
    {
        return [
            'positions' => ['required', 'array']
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request): void
    {
        $this->initialisationFromShop($webpage->shop, $request);
        $this->handle($webpage, $this->validatedData);
    }

    public function action(Webpage $webpage, array $modelData): Webpage
    {
        $this->asAction = true;

        $this->initialisationFromShop($webpage->shop, $modelData);

        return $this->handle($webpage, $this->validatedData);
    }

}

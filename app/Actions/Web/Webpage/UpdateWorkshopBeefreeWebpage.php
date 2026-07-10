<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Fri Jul 10 2026
 * Copyright (c) 2026, Eka Yudinata
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;

class UpdateWorkshopBeefreeWebpage extends OrgAction
{
    use WithActionUpdate;

    public function handle(Webpage $webpage, array $modelData): Webpage
    {
        $snapshot = $webpage->unpublishedSnapshot;

        abort_unless($snapshot, 422, 'No unpublished snapshot exists for this webpage');

        $this->update($snapshot, $modelData);

        $pageJson = $modelData['layout'];
        $layout = array_merge(['web_blocks' => []], $pageJson);

        $modelData['layout'] = $layout;

        $this->update($snapshot, $modelData);

        return $webpage;
    }

    public function rules(): array
    {
        return [
            'layout'          => ['required'],
            'compiled_layout' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $this->validatedData);
    }
}

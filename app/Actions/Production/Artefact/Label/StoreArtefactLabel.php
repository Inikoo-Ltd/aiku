<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Thu, 10 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\OrgAction;
use App\Http\Resources\Production\ArtefactLabelResource;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactLabel;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreArtefactLabel extends OrgAction
{
    use WithArtefactLabelLayout;

    public function handle(Artefact $artefact, array $modelData): ArtefactLabel
    {
        $artwork = Arr::pull($modelData, 'artwork');

        $label = $artefact->labels()->create([
            'group_id'        => $artefact->group_id,
            'organisation_id' => $artefact->organisation_id,
            'name'            => Arr::get($modelData, 'name'),
            'layout'          => $this->packLayout($modelData),
        ]);

        if ($artwork) {
            $label->update(['artwork_id' => $this->saveArtwork($artefact, $artwork)->id]);
        }

        return $label;
    }

    public function rules(): array
    {
        return array_merge(
            [
                'name'    => ['required', 'string', 'max:255'],
                'artwork' => $this->artworkFileRules(),
            ],
            $this->labelLayoutRules()
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_rd.{$this->production->id}.view",
        ]);
    }

    public function action(Artefact $artefact, array $modelData): ArtefactLabel
    {
        $this->asAction = true;
        $this->initialisationFromProduction($artefact->production, $modelData);

        return $this->handle($artefact, $this->validatedData);
    }

    public function asController(Artefact $artefact, ActionRequest $request): ArtefactLabel
    {
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $this->validatedData);
    }

    public function jsonResponse(ArtefactLabel $artefactLabel): ArtefactLabelResource
    {
        return ArtefactLabelResource::make($artefactLabel);
    }
}

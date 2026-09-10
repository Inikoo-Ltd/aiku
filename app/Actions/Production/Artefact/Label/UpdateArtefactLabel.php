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

class UpdateArtefactLabel extends OrgAction
{
    use WithArtefactLabelLayout;

    public function handle(ArtefactLabel $artefactLabel, array $modelData): ArtefactLabel
    {
        $artwork       = Arr::pull($modelData, 'artwork');
        $removeArtwork = filter_var(Arr::pull($modelData, 'remove_artwork', false), FILTER_VALIDATE_BOOLEAN);

        $changes = ['layout' => $this->packLayout($modelData)];

        if (Arr::has($modelData, 'name')) {
            $changes['name'] = Arr::get($modelData, 'name');
        }

        if ($artwork) {
            $changes['artwork_id'] = $this->saveArtwork($artefactLabel->artefact, $artwork)->id;
        } elseif ($removeArtwork) {
            $changes['artwork_id'] = null;
        }

        $artefactLabel->update($changes);

        return $artefactLabel->refresh();
    }

    public function rules(): array
    {
        return array_merge(
            [
                'name'           => ['sometimes', 'required', 'string', 'max:255'],
                'artwork'        => $this->artworkFileRules(),
                'remove_artwork' => ['sometimes', 'boolean'],
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

    public function action(ArtefactLabel $artefactLabel, array $modelData): ArtefactLabel
    {
        $this->asAction = true;
        $this->initialisationFromProduction($artefactLabel->artefact->production, $modelData);

        return $this->handle($artefactLabel, $this->validatedData);
    }

    public function asController(Artefact $artefact, ArtefactLabel $label, ActionRequest $request): ArtefactLabel
    {
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($label, $this->validatedData);
    }

    public function jsonResponse(ArtefactLabel $artefactLabel): ArtefactLabelResource
    {
        return ArtefactLabelResource::make($artefactLabel);
    }
}

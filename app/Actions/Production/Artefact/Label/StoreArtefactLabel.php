<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Thu, 10 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use App\Http\Resources\Production\ArtefactLabelResource;
use App\Models\Inventory\OrgStock;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactLabel;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreArtefactLabel extends OrgAction
{
    use WithArtefactLabelLayout;
    use WithArtefactLabelAuthorisation;

    public function handle(OrgStock $orgStock, array $modelData): ArtefactLabel
    {
        $artwork = Arr::pull($modelData, 'artwork');

        $label = $orgStock->labels()->create([
            'group_id'        => $orgStock->group_id,
            'organisation_id' => $orgStock->organisation_id,
            'artefact_id'     => Artefact::where('org_stock_id', $orgStock->id)->value('id'),
            'name'            => Arr::get($modelData, 'name'),
            'layout'          => $this->packLayout($modelData),
            'state'           => ArtefactLabelStateEnum::RAW,
        ]);

        if ($artwork) {
            $label->update(['artwork_id' => $this->saveArtwork($orgStock, $artwork)->id]);
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

        return $this->canEditLabels($request);
    }

    public function action(OrgStock $orgStock, array $modelData): ArtefactLabel
    {
        $this->asAction = true;
        $this->initialisation($orgStock->organisation, $modelData);

        return $this->handle($orgStock, $this->validatedData);
    }

    public function asController(Artefact $artefact, ActionRequest $request): ArtefactLabel
    {
        $this->initialisationFromProduction($artefact->production, $request);
        abort_unless($artefact->orgStock, 422, __('This artefact has no SKO, so it cannot carry labels yet.'));

        return $this->handle($artefact->orgStock, $this->validatedData);
    }

    public function inOrgStock(OrgStock $orgStock, ActionRequest $request): ArtefactLabel
    {
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle($orgStock, $this->validatedData);
    }

    public function jsonResponse(ArtefactLabel $artefactLabel): ArtefactLabelResource
    {
        return ArtefactLabelResource::make($artefactLabel);
    }
}

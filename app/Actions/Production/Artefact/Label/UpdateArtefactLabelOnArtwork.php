<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactLabelInformationEnum;
use App\Http\Resources\Production\ArtefactLabelResource;
use App\Models\Inventory\OrgStock;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactLabel;
use Lorisleiva\Actions\ActionRequest;

/**
 * A supplier's artwork often already prints the ingredients or the pictograms, so a compliance worker
 * confirms those here instead of placing the same text on top of them.
 */
class UpdateArtefactLabelOnArtwork extends OrgAction
{
    use WithArtefactLabelAuthorisation;

    public function handle(ArtefactLabel $artefactLabel, array $modelData): ArtefactLabel
    {
        $artefactLabel->update([
            'on_artwork' => array_values(array_unique($modelData['on_artwork'] ?? [])),
        ]);

        return $artefactLabel;
    }

    public function rules(): array
    {
        return [
            'on_artwork'   => ['present', 'array'],
            'on_artwork.*' => ArtefactLabelInformationEnum::sourceRule(),
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canEditLabels($request);
    }

    public function action(ArtefactLabel $artefactLabel, array $modelData): ArtefactLabel
    {
        $this->asAction = true;
        $this->initialisation($artefactLabel->organisation, $modelData);

        return $this->handle($artefactLabel, $this->validatedData);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Artefact $artefact, ArtefactLabel $label, ActionRequest $request): ArtefactLabel
    {
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($label, $this->validatedData);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgStock(OrgStock $orgStock, ArtefactLabel $label, ActionRequest $request): ArtefactLabel
    {
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle($label, $this->validatedData);
    }

    public function jsonResponse(ArtefactLabel $artefactLabel): ArtefactLabelResource
    {
        return ArtefactLabelResource::make($artefactLabel);
    }
}

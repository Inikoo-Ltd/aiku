<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Mon, 14 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactLabelInformationEnum;
use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use App\Http\Resources\Production\ArtefactLabelResource;
use App\Models\Helpers\Language;
use App\Models\Inventory\OrgStock;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactLabel;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class PublishArtefactLabel extends OrgAction
{
    use WithArtefactLabelAuthorisation;

    /**
     * @throws ValidationException
     */
    public function handle(ArtefactLabel $artefactLabel): ArtefactLabel
    {
        if ($missing = $artefactLabel->missingMandatoryInformation()) {
            $languageNames = Language::pluck('name', 'code')->all();

            throw ValidationException::withMessages([
                'label' => __('The label is missing mandatory information: :information', [
                    'information' => implode(', ', array_map(
                        fn (string $information) => ArtefactLabelInformationEnum::label($information, $languageNames),
                        $missing
                    )),
                ]),
            ]);
        }

        $artefactLabel->update([
            'state'        => ArtefactLabelStateEnum::PUBLISHED,
            'published_at' => now(),
        ]);

        return $artefactLabel;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canPublishLabels($request);
    }

    public function action(ArtefactLabel $artefactLabel): ArtefactLabel
    {
        $this->asAction = true;
        $this->initialisation($artefactLabel->organisation, []);

        return $this->handle($artefactLabel);
    }

    public function asController(Artefact $artefact, ArtefactLabel $label, ActionRequest $request): ArtefactLabel
    {
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($label);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgStock(OrgStock $orgStock, ArtefactLabel $label, ActionRequest $request): ArtefactLabel
    {
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle($label);
    }

    public function jsonResponse(ArtefactLabel $artefactLabel): ArtefactLabelResource
    {
        return ArtefactLabelResource::make($artefactLabel);
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Actions\Production\RawMaterial\UpdateRawMaterial;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class SetArtefactAsMix extends OrgAction
{
    public function handle(Artefact $artefact, bool $isMix): ?RawMaterial
    {
        $linked = RawMaterial::where('artefact_id', $artefact->id)->first();

        if (!$isMix) {
            if ($linked) {
                UpdateRawMaterial::make()->action($linked, ['artefact_id' => null]);
            }

            return null;
        }

        if ($linked) {
            return $linked;
        }

        $existing = RawMaterial::where('organisation_id', $artefact->organisation_id)
            ->whereNull('artefact_id')
            ->where(function ($query) use ($artefact) {
                $query->where('code', $artefact->code);
                if ($artefact->org_stock_id) {
                    $query->orWhere('org_stock_id', $artefact->org_stock_id);
                }
            })
            ->first();

        if ($existing) {
            return UpdateRawMaterial::make()->action($existing, ['artefact_id' => $artefact->id, 'type' => RawMaterialTypeEnum::INTERMEDIATE]);
        }

        return StoreRawMaterial::make()->action($artefact->production, [
            'type'         => RawMaterialTypeEnum::INTERMEDIATE,
            'code'         => $artefact->code,
            'description'  => $artefact->name,
            'unit'         => RawMaterialUnitEnum::UNIT,
            'org_stock_id' => $artefact->org_stock_id,
            'artefact_id'  => $artefact->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'is_mix' => ['required', 'boolean'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_rd.{$this->production->id}.edit",
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function action(Artefact $artefact, bool $isMix): ?RawMaterial
    {
        $this->asAction   = true;
        $this->production = $artefact->production;
        $this->initialisation($artefact->organisation, ['is_mix' => $isMix]);

        return $this->handle($artefact, $isMix);
    }

    public function asController(Organisation $organisation, Production $production, Artefact $artefact, ActionRequest $request): ?RawMaterial
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($artefact, $this->validatedData['is_mix']);
    }

    public function htmlResponse(?RawMaterial $rawMaterial): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => $rawMaterial ? __('Artefact is now a mix, it will show on the Mixes board when needed') : __('Artefact is no longer a mix'),
        ]);
    }
}

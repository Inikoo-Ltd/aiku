<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Tue, 16 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\UI;

use App\Http\Resources\Production\ArtefactLabelResource;
use App\Models\Production\Artefact;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtefactLabels
{
    use AsObject;

    public function handle(Artefact $artefact): array
    {
        return [
            'route' => [
                'name'       => 'grp.models.artefact.label_sheet',
                'parameters' => ['artefact' => $artefact->id]
            ],
            'store_route' => [
                'name'       => 'grp.models.artefact.labels.store',
                'parameters' => ['artefact' => $artefact->id]
            ],
            'update_route' => [
                'name'       => 'grp.models.artefact.labels.update',
                'parameters' => ['artefact' => $artefact->id]
            ],
            'delete_route' => [
                'name'       => 'grp.models.artefact.labels.delete',
                'parameters' => ['artefact' => $artefact->id]
            ],
            'publish_route' => [
                'name'       => 'grp.models.artefact.labels.publish',
                'parameters' => ['artefact' => $artefact->id]
            ],
            'unpublish_route' => [
                'name'       => 'grp.models.artefact.labels.unpublish',
                'parameters' => ['artefact' => $artefact->id]
            ],
            'batch_code'  => $this->getPlaceholderBatchCode($artefact),
            'expiry_date' => $this->getPlaceholderExpiryDate(),
            'barcode'     => $this->getBarcode($artefact),
            'labels'      => ArtefactLabelResource::collection($artefact->labels()->with('artwork')->get())->resolve(),
        ];
    }

    /**
     * The outer CODE 128 printed on the packing, falling back to the unit EAN13 for the org stocks
     * that only carry that one.
     */
    private function getBarcode(Artefact $artefact): string
    {
        $orgStock = $artefact->orgStock;

        if (!$orgStock) {
            return '';
        }

        return $orgStock->barcode ?: ($orgStock->unit_barcode ?: '');
    }

    /**
     * Artefacts have no batch code column yet, this is the stand in until the real one is stored.
     */
    private function getPlaceholderBatchCode(Artefact $artefact): string
    {
        return strtoupper($artefact->code).'-'.now()->format('ymd');
    }

    /**
     * Artefacts have no expiry date column yet, this is the stand in until the real one is stored.
     */
    private function getPlaceholderExpiryDate(): string
    {
        return now()->addYear()->format('d/m/Y');
    }
}

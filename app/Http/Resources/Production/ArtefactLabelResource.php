<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Thu, 10 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Http\Resources\Production;

use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use App\Models\Production\ArtefactLabel;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtefactLabelResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ArtefactLabel $label */
        $label = $this;

        return [
            'id'           => $label->id,
            'name'         => $label->name,
            'layout'       => $label->layout,
            'state'        => $label->state,
            'state_label'  => ArtefactLabelStateEnum::labels()[$label->state->value],
            'published_at' => $label->published_at,
            'pdf_url'      => $label->state === ArtefactLabelStateEnum::PUBLISHED
                ? ($label->artefact_id
                    ? route('grp.models.artefact.labels.pdf', ['artefact' => $label->artefact_id, 'label' => $label->id])
                    : route('grp.models.org_stock.labels.pdf', ['orgStock' => $label->org_stock_id, 'label' => $label->id]))
                : null,
            'on_artwork'          => $label->on_artwork ?? [],
            'missing_information' => $label->missingMandatoryInformation(),
            'artwork'      => $label->artwork ? [
                'name'      => $label->artwork->name,
                'size'      => $label->artwork->size,
                'mime_type' => $label->artwork->mime_type,
                'url'       => route('grp.media.download', ['media' => $label->artwork->ulid]),
            ] : null,
            'updated_at'   => $label->updated_at,
        ];
    }
}

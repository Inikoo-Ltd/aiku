<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Thu, 10 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Http\Resources\Production;

use App\Models\Production\ArtefactLabel;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtefactLabelResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ArtefactLabel $label */
        $label = $this;

        return [
            'id'         => $label->id,
            'name'       => $label->name,
            'layout'     => $label->layout,
            'artwork'    => $label->artwork ? [
                'name'      => $label->artwork->name,
                'size'      => $label->artwork->size,
                'mime_type' => $label->artwork->mime_type,
                'url'       => route('grp.media.download', ['media' => $label->artwork->ulid]),
            ] : null,
            'updated_at' => $label->updated_at,
        ];
    }
}

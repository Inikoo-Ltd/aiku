<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 19:19:20 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $size
 * @property mixed $name
 * @property mixed $id
 */
class TaxNumberResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'country_code'  => $this->country_code,
            'number'  => $this->number,
            'type' => $this->type,
            'status' => $this->status,
            'valid' => $this->valid
        ];
    }
}
 
<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Jun 2023 13:57:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadRecordsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var \App\Models\Helpers\UploadRecord $record */
        $record = $this;

        return [
            'id' => $record->id,
            'original_filename' => $record->excel->original_filename,
            'row_number' => $record->row_number,
            'errors' => $record->errors,
            'fail_column' => $record->fail_column,
            'status' => $record->status,
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
        ];
    }
}

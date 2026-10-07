<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Upload;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadReportResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Upload $upload */
        $upload = $this->resource;

        return [
            'id'                => $upload->id,
            'original_filename' => $upload->original_filename,
            'created_at'        => $upload->created_at,
            'uploaded_by'       => $upload->user?->contact_name ?: $upload->user?->username,
            'number_rows'       => $upload->number_rows,
            'number_success'    => $upload->number_success,
            'number_fails'      => $upload->number_fails,
            'fail_reasons'      => $upload->failReasons(),
            'records_route'     => [
                'name'       => 'grp.helpers.uploads.records.index',
                'parameters' => ['upload' => $upload->id],
            ],
            'download_route'    => [
                'name'       => 'grp.helpers.uploads.records.download',
                'parameters' => ['upload' => $upload->id],
            ],
        ];
    }
}

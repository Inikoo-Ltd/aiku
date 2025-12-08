<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Jun 2023 13:57:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Upload;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Upload $upload */
        $upload = $this;

        return [
            'id' => $upload->id,
            'type' => $upload->model,
            'uploaded_at' => $upload->uploaded_at,
            'original_filename' => $upload->original_filename,
            'filename' => $upload->filename,
            'number_rows' => $upload->number_rows,
            'number_success' => $upload->number_success,
            'number_fails' => $upload->number_fails,
            'path' => $upload->path,
            'download_route' => match (request()->routeIs('retina.*')) {
                true => [
                    'name' => 'retina.helpers.uploads.records.show',
                    'parameters' => $upload->id,
                ],
                default => [
                    'name' => 'grp.helpers.uploads.records.show',
                    'parameters' => $upload->id,
                ]
            },
            'show_route' => match (request()->routeIs('retina.*')) {
                true => [
                    'name' => 'retina.helpers.uploads.records.download',
                    'parameters' => $upload->id,
                ],
                default => [
                    'name' => 'grp.helpers.uploads.records.download',
                    'parameters' => $upload->id,
                ]
            },

        ];
    }
}

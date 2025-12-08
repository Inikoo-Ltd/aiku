<?php

/*
 * author Arya Permana - Kirin
 * created on 22-01-2025-16h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Helpers;

use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Upload;
use Illuminate\Http\Resources\Json\JsonResource;

class PalletReturnItemUploadsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Upload $upload */
        $upload = $this;

        return [
            'id' => $upload->id,
            'uploaded_at' => $upload->created_at,
            'original_filename' => $upload->original_filename,
            'filename' => $upload->filename,
            'number_rows' => $upload->number_rows,
            'number_success' => $upload->number_success,
            'number_fails' => $upload->number_fails,
            'path' => $upload->path,
        ];
    }
}

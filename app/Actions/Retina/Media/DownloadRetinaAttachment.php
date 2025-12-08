<?php

/*
 * author Arya Permana - Kirin
 * created on 14-02-2025-13h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Media;

use App\Models\Helpers\Media;
use Lorisleiva\Actions\Concerns\AsAction;

class DownloadRetinaAttachment
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(Media $media)
    {
        return response()->download($media->getPath(), $media->file_name);
    }
}

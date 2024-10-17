<?php
/*
 * author Arya Permana - Kirin
 * created on 17-10-2024-14h-32m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Helpers\Media\UI;

use App\Models\Helpers\Media;
use Lorisleiva\Actions\Concerns\AsAction;

class DownloadAttachment
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

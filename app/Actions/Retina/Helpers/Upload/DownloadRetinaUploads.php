<?php

/*
 * author Arya Permana - Kirin
 * created on 22-01-2025-09h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Helpers\Upload;

use App\Models\Helpers\Upload;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadRetinaUploads
{
    use AsAction;
    use WithAttributes;

    private bool $asAction = false;

    public function handle(Upload $upload): BinaryFileResponse
    {
        return response()->download(storage_path('app/'.$upload->path.'/'.$upload->filename));
    }

    public function asController(Upload $upload): BinaryFileResponse
    {
        return $this->handle($upload);
    }
}

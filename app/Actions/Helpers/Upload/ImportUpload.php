<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 13:30:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Upload;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use Maatwebsite\Excel\Facades\Excel;

class ImportUpload
{
    use AsAction;

    public string $jobQueue = 'default-long';

    public function handle(UploadedFile|string $file, $import): void
    {
        $realPath = storage_path('app/' . $file);

        $readerType = null;
        if ($file instanceof UploadedFile) {
            $extension = strtolower($file->getClientOriginalExtension());
            $readerType = match ($extension) {
                'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
                'xls' => \Maatwebsite\Excel\Excel::XLS,
                'csv' => \Maatwebsite\Excel\Excel::CSV,
                'txt' => \Maatwebsite\Excel\Excel::CSV,
                default => null,
            };
        }

        Excel::import(
            $import,
            is_string($file) ? $realPath : $file->path(),
            null,
            $readerType,
        );

        if (is_string($file)) {
            Storage::delete($file);
        }
    }

}

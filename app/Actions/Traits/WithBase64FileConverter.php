<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jul 2023 13:31:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\HumanResources\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

trait WithBase64FileConverter
{
    public function convertBase64ToFile($base64File, Employee $model): UploadedFile
    {
        $fileData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64File));

        $tmpFilePath = sys_get_temp_dir().'/'.Str::uuid()->toString();
        file_put_contents($tmpFilePath, $fileData);

        $tmpFile = new File($tmpFilePath);

        return new UploadedFile(
            $tmpFile->getPathname(),
            $model->slug.'-'.now()->format('Y-m-d-H-i-s').'.'.$tmpFile->getExtension(),
            $tmpFile->getMimeType(),
            0,
            false
        );
    }
}

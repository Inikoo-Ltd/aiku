<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:48:13 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Production\RawMaterial;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\OrgAction;
use App\Actions\Traits\WithImportModel;
use App\Http\Resources\Helpers\UploadsResource;
use App\Models\Helpers\Upload;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class ImportRawMaterial extends OrgAction
{
    use WithImportModel;

    public function handle(Production $production, $file): Upload
    {
        $upload = StoreUpload::run($file, RawMaterial::class);

        if ($this->isSync) {
            ImportUpload::run(
                $file,
                new RawMaterialImport($production, $upload)
            );
            $upload->refresh();
        } else {
            ImportUpload::dispatch(
                $this->tmpPath.$upload->filename,
                new RawMaterialImport($production, $upload)
            );
        }

        return $upload;
    }

    public function authorize(ActionRequest $request): bool
    {

        return true;
    }

    public function asController(Production $production, ActionRequest $request): Upload
    {
        $request->validate();
        $file = $request->file('file');
        Storage::disk('local')->put($this->tmpPath, $file);

        return $this->handle($production, $file);
    }

    public function jsonResponse(Upload $upload): array
    {
        return UploadsResource::make($upload)->getArray();
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Aug 2023 08:09:28 Malaysia Time, Pantai Lembeng, Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Upload;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Upload;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\CRM\WebUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreUpload extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Group|Organisation $parent, array $modelData): Upload
    {
        data_set($modelData, 'group_id', $parent instanceof Group ? $parent->id : $parent->group_id);

        if ($parent instanceof Organisation) {
            data_set($modelData, 'organisation_id', $parent->id);
        }


        if (!Arr::exists($modelData, 'user_id')) {
            /** @var User|WebUser $user */
            $user = request()->user();

            if ($user instanceof User) {
                data_set($modelData, 'user_id', $user->id);
            } elseif ($user instanceof WebUser) {
                data_set($modelData, 'web_user_id', $user->id);
            }
        }


        /** @var Upload $upload */
        $upload = $parent->uploads()->create($modelData);

        return $upload;
    }

    public function rules(): array
    {
        $rules = [
            'model' => 'required|string',
            'parent_type' => 'required|string',
            'parent_id' => 'required|numeric',
        ];

        if (!$this->strict) {
            $rules['number_rows']       = ['sometimes', 'numeric'];
            $rules['number_success']    = ['sometimes', 'numeric'];
            $rules['number_fails']      = ['sometimes', 'numeric'];
            $rules['user_id']           = ['sometimes', 'exists:users,id'];
            $rules['web_user_id']       = ['sometimes', 'exists:web_users,id'];
            $rules['original_filename'] = ['sometimes', 'string'];
            $rules['filename']          = ['sometimes', 'string'];
            $rules['filesize']          = ['sometimes', 'numeric'];
            $rules['created_at']        = ['sometimes', 'date'];
            $rules['uploaded_at']       = ['sometimes', 'date'];
            $rules['fetched_at']        = ['sometimes', 'date'];
            $rules['source_id']         = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }

    public function fromFile(Group|Organisation|Shop $parent, $file, array $modelData): Upload
    {
        $this->initialise($parent, $modelData);

        $modelData = $this->validatedData;

        $filename = $file->hashName();
        $path     = 'excel-uploads/org/'.Str::lower($modelData['model']);

        Storage::disk('excel-uploads')->put($path, $file);

        data_set($modelData, 'original_filename', $file->getClientOriginalName());
        data_set($modelData, 'filename', $filename);
        data_set($modelData, 'filesize', $file->getSize());

        data_set($modelData, 'path', $path);

        return $this->handle($parent, $modelData);
    }

    public function action(Group|Organisation|Shop $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Upload
    {
        if (!$audit) {
            Upload::disableAuditing();
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialise($parent, $modelData);


        return $this->handle($parent, $this->validatedData);
    }

    protected function initialise(Group|Organisation|Shop $parent, array $modelData): void
    {
        switch ($parent) {
            case $parent instanceof Group:
                $this->initialisationFromGroup($parent, $modelData);
                break;
            case $parent instanceof Organisation:
                $this->initialisation($parent, $modelData);
                break;
            case $parent instanceof Shop:
                $this->initialisationFromShop($parent, $modelData);
                break;
        }
    }


}

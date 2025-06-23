<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jul 2024 18:46:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group;

use App\Actions\GrpAction;
use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SysAdmin\Group\GroupResource;
use App\Models\SysAdmin\Group;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateGroupSettings extends GrpAction
{
    use WithActionUpdate;



    public function handle(Group $group, array $modelData): Group
    {

        if (Arr::has($modelData, 'logo')) {
            /** @var UploadedFile $image */
            $image = Arr::get($modelData, 'logo');
            data_forget($modelData, 'logo');
            $imageData    = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
            ];
            $group = SaveModelImage::run(
                model: $group,
                imageData: $imageData,
                scope: 'logo'
            );
        }
        Cache::forget('bound-group-'.$group->id);

        $groupSettings = $group->settings;
        if (Arr::has($modelData, 'client_id')) {
            data_set($groupSettings, 'beefree.client_id', Arr::get($modelData, 'client_id'));
            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'client_id');
        }
        if (Arr::has($modelData, 'client_secret')) {
            data_set($groupSettings, 'beefree.client_secret', Arr::get($modelData, 'client_secret'));
            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'client_secret');
        }
        if (Arr::has($modelData, 'grant_type')) {
            data_set($groupSettings, 'beefree.grant_type', Arr::get($modelData, 'grant_type'));
            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'grant_type');
        }
        if (Arr::exists($modelData, 'treblle_project_id')) {
            data_set($modelData, "settings.treblle.project_id", Arr::pull($modelData, 'treblle_project_id'));
        }

        if (Arr::exists($modelData, 'treblle_api_key')) {
            data_set($modelData, "settings.treblle.api_key", Arr::pull($modelData, 'treblle_api_key'));
        }

        return $this->update($group, $modelData, ['settings']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("sysadmin.edit");
    }

    public function rules(): array
    {
        return [
            'name'                    => ['sometimes', 'required', 'string', 'max:64'],
            'logo'                    => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'client_id'                         => ['sometimes', 'string', 'nullable'],
            'client_secret'                     => ['sometimes', 'string', 'nullable'],
            'grant_type'                        => ['sometimes', 'string', 'nullable'],
            'treblle_api_key'          => ['sometimes', 'nullable', 'string'],
            'treblle_project_id'       => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function action(Group $group, array $modelData): Group
    {
        $this->asAction = true;
        $this->initialisation($group, $modelData);


        return $this->handle($group, $this->validatedData);
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($this->group, $this->validatedData);
    }


    public function jsonResponse(Group $group): GroupResource
    {
        return new GroupResource($group);
    }
}

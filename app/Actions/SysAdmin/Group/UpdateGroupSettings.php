<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jul 2024 18:46:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group;

use App\Actions\Audits\DispatchSimpleAudit;
use App\Actions\GrpAction;
use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Ses\SesRegionEnum;
use App\Http\Resources\SysAdmin\Group\GroupResource;
use App\Models\SysAdmin\Group;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
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
        if (Arr::has($modelData, 'printnode_api_key')) {
            data_set($groupSettings, 'printnode.apikey', Arr::get($modelData, 'printnode_api_key'));
            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'printnode_api_key');
        }
        if (Arr::has($modelData, 'print_by_printnode')) {
            data_set($groupSettings, 'printnode.print_by_printnode', Arr::get($modelData, 'print_by_printnode'));
            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'print_by_printnode');
        }
        if (Arr::has($modelData, 'jira_base_url')) {
            data_set($groupSettings, 'jira.base_url', Arr::get($modelData, 'jira_base_url'));
            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'jira_base_url');
        }
        if (Arr::has($modelData, 'jira_email')) {
            data_set($groupSettings, 'jira.email', Arr::get($modelData, 'jira_email'));
            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'jira_email');
        }
        if (Arr::has($modelData, 'jira_api_token')) {
            data_set($groupSettings, 'jira.api_token', Arr::get($modelData, 'jira_api_token'));
            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'jira_api_token');
        }

        if (Arr::has($modelData, 'access_id')) {
            $oldAccessId = Arr::get($groupSettings, 'email.provider.access_id');
            $newAccessId = Arr::get($modelData, 'access_id');
            
            data_set($groupSettings, 'email.provider.access_id', $newAccessId);
            $group->update(['settings' => $groupSettings]);

            DispatchSimpleAudit::run(
                auditableModel: $group,
                logKey: 'email.provider.failover.access_id',
                oldValue: $oldAccessId,
                newValue: $newAccessId,
                eventName: 'updated',
            );

            data_forget($modelData, 'access_id');
        }
        if (Arr::has($modelData, 'access_key')) {
            data_set($groupSettings, 'email.provider.access_key', Arr::get($modelData, 'access_key'));

            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'access_key');
        }
        if (Arr::has($modelData, 'region')) {
            data_set($groupSettings, 'email.provider.region', Arr::get($modelData, 'region'));
            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'region');
        }
        if (Arr::has($modelData, 'user_notification_access_id')) {
            data_set($groupSettings, 'user_notification.provider.access_id', Arr::get($modelData, 'user_notification_access_id'));
            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'user_notification_access_id');
        }
        if (Arr::has($modelData, 'user_notification_access_key')) {
            data_set($groupSettings, 'user_notification.provider.access_key', Arr::get($modelData, 'user_notification_access_key'));
            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'user_notification_access_key');
        }
        if (Arr::has($modelData, 'user_notification_region')) {
            data_set($groupSettings, 'user_notification.provider.region', Arr::get($modelData, 'user_notification_region'));
            $group->update(['settings' => $groupSettings]);
            data_forget($modelData, 'user_notification_region');
        }

        return $this->update($group, $modelData);
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
            'extra_languages'                   => ['sometimes', 'array', 'nullable'],
            'printnode_api_key' => ['sometimes', 'string', 'nullable'],
            'print_by_printnode' => ['sometimes', 'boolean', 'nullable'],
            'jira_base_url'      => ['sometimes', 'nullable', 'url'],
            'jira_email'         => ['sometimes', 'nullable', 'email'],
            'jira_api_token'     => ['sometimes', 'nullable', 'string'],
            'access_id'                    => ['sometimes', 'string', 'nullable'],
            'access_key'                   => ['sometimes', 'string', 'nullable'],
            'region'                       => ['sometimes', 'nullable', Rule::enum(SesRegionEnum::class)],
            'user_notification_access_id' => ['sometimes', 'string', 'nullable'],
            'user_notification_access_key' => ['sometimes', 'string', 'nullable'],
            'user_notification_region'     => ['sometimes', 'nullable', Rule::enum(SesRegionEnum::class)],

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

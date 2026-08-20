<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jul 2024 18:46:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group;

use App\Actions\OrgAction;
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

class UpdateGroupSettings extends OrgAction
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

        if (Arr::has($modelData, 'client_id')) {
            data_set($modelData, 'settings.beefree.client_id', Arr::pull($modelData, 'client_id'));
        }
        if (Arr::has($modelData, 'client_secret')) {
            data_set($modelData, 'settings.beefree.client_secret', Arr::pull($modelData, 'client_secret'));
        }
        if (Arr::has($modelData, 'page_builder_client_id')) {
            data_set($modelData, 'settings.beefree.page_builder.client_id', Arr::pull($modelData, 'page_builder_client_id'));
        }
        if (Arr::has($modelData, 'page_builder_client_secret')) {
            data_set($modelData, 'settings.beefree.page_builder.client_secret', Arr::pull($modelData, 'page_builder_client_secret'));
        }
        if (Arr::has($modelData, 'grant_type')) {
            data_set($modelData, 'settings.beefree.grant_type', Arr::pull($modelData, 'grant_type'));
        }
        if (Arr::has($modelData, 'printnode_api_key')) {
            data_set($modelData, 'settings.printnode.apikey', Arr::pull($modelData, 'printnode_api_key'));
        }
        if (Arr::has($modelData, 'print_by_printnode')) {
            data_set($modelData, 'settings.printnode.print_by_printnode', Arr::pull($modelData, 'print_by_printnode'));
        }
        if (Arr::has($modelData, 'timezones')) {
            data_set($modelData, 'settings.timezones', array_values(Arr::pull($modelData, 'timezones') ?? []));
        }
        if (Arr::has($modelData, 'official_stock_valuation_method')) {
            data_set($modelData, 'settings.inventory.official_valuation_method', Arr::pull($modelData, 'official_stock_valuation_method'));
        }
        if (Arr::has($modelData, 'jira_base_url')) {
            data_set($modelData, 'settings.jira.base_url', Arr::pull($modelData, 'jira_base_url'));
        }
        if (Arr::has($modelData, 'jira_email')) {
            data_set($modelData, 'settings.jira.email', Arr::pull($modelData, 'jira_email'));
        }
        if (Arr::has($modelData, 'jira_api_token')) {
            data_set($modelData, 'settings.jira.api_token', Arr::pull($modelData, 'jira_api_token'));
        }

        if (Arr::exists($modelData, 'access_id')) {
            data_set($modelData, 'settings.email.provider.failover.access_id', Arr::pull($modelData, 'access_id'));
        }
        if (Arr::exists($modelData, 'access_key')) {
            data_set($modelData, 'settings.email.provider.failover.access_key', Arr::pull($modelData, 'access_key'));
        }
        if (Arr::exists($modelData, 'region')) {
            data_set($modelData, 'settings.email.provider.failover.region', Arr::pull($modelData, 'region'));
        }
        if (Arr::exists($modelData, 'customer_notification_access_id')) {
            data_set($modelData, 'settings.email.provider.customer_notification.access_id', Arr::pull($modelData, 'customer_notification_access_id'));
        }
        if (Arr::exists($modelData, 'customer_notification_access_key')) {
            data_set($modelData, 'settings.email.provider.customer_notification.access_key', Arr::pull($modelData, 'customer_notification_access_key'));
        }
        if (Arr::exists($modelData, 'customer_notification_region')) {
            data_set($modelData, 'settings.email.provider.customer_notification.region', Arr::pull($modelData, 'customer_notification_region'));
        }
        if (Arr::exists($modelData, 'user_notification_access_id')) {
            data_set($modelData, 'settings.email.provider.user_notification.access_id', Arr::pull($modelData, 'user_notification_access_id'));
        }
        if (Arr::exists($modelData, 'user_notification_access_key')) {
            data_set($modelData, 'settings.email.provider.user_notification.access_key', Arr::pull($modelData, 'user_notification_access_key'));
        }
        if (Arr::exists($modelData, 'user_notification_region')) {
            data_set($modelData, 'settings.email.provider.user_notification.region', Arr::pull($modelData, 'user_notification_region'));
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
            'page_builder_client_id'            => ['sometimes', 'string', 'nullable'],
            'page_builder_client_secret'        => ['sometimes', 'string', 'nullable'],
            'grant_type'                        => ['sometimes', 'string', 'nullable'],
            'extra_languages'                   => ['sometimes', 'array', 'nullable'],
            'printnode_api_key' => ['sometimes', 'string', 'nullable'],
            'print_by_printnode' => ['sometimes', 'boolean', 'nullable'],
            'timezones'          => ['sometimes', 'array'],
            'timezones.*'        => ['string', 'timezone'],
            'official_stock_valuation_method' => ['sometimes', Rule::in([
                \App\Enums\Inventory\OrgStock\OrgStockValuationMethodEnum::FIFO->value,
                \App\Enums\Inventory\OrgStock\OrgStockValuationMethodEnum::WAC->value,
            ])],
            'jira_base_url'      => ['sometimes', 'nullable', 'url'],
            'jira_email'         => ['sometimes', 'nullable', 'email'],
            'jira_api_token'     => ['sometimes', 'nullable', 'string'],
            'access_id'                    => ['sometimes', 'string', 'nullable'],
            'access_key'                   => ['sometimes', 'string', 'nullable'],
            'region'                       => ['sometimes', 'nullable', Rule::enum(SesRegionEnum::class)],
            'customer_notification_access_id'  => ['sometimes', 'string', 'nullable'],
            'customer_notification_access_key' => ['sometimes', 'string', 'nullable'],
            'customer_notification_region'     => ['sometimes', 'nullable', Rule::enum(SesRegionEnum::class)],
            'user_notification_access_id'  => ['sometimes', 'string', 'nullable'],
            'user_notification_access_key' => ['sometimes', 'string', 'nullable'],
            'user_notification_region'     => ['sometimes', 'nullable', Rule::enum(SesRegionEnum::class)],

        ];
    }

    public function action(Group $group, array $modelData): Group
    {
        $this->asAction = true;
        $this->initialisationFromGroup($group, $modelData);


        return $this->handle($group, $this->validatedData);
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group, $this->validatedData);
    }


    public function jsonResponse(Group $group): GroupResource
    {
        return new GroupResource($group);
    }
}

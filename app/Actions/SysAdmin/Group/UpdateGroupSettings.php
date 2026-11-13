<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jul 2024 18:46:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group;

use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
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

        if (Arr::has($modelData, 'staff_chat_quick_replies')) {
            $quickReplies = collect(explode("\n", (string) Arr::pull($modelData, 'staff_chat_quick_replies')))
                ->map(fn ($line) => trim($line))
                ->filter()
                ->take(12)
                ->values()
                ->all();
            data_set($modelData, 'settings.staff_chat.quick_replies', $quickReplies);
        }
        if (Arr::has($modelData, 'shopping_assistant_name')) {
            data_set($modelData, 'settings.shopping_assistant.name', trim((string) Arr::pull($modelData, 'shopping_assistant_name')) ?: null);
        }
        if (Arr::has($modelData, 'shopping_assistant_weekly_summary_user_ids')) {
            data_set($modelData, 'settings.shopping_assistant.weekly_summary_user_ids', array_values(array_map('intval', Arr::pull($modelData, 'shopping_assistant_weekly_summary_user_ids') ?? [])));
        }
        if (Arr::has($modelData, 'shopping_assistant_unmet_needs_user_ids')) {
            data_set($modelData, 'settings.shopping_assistant.unmet_needs_user_ids', array_values(array_map('intval', Arr::pull($modelData, 'shopping_assistant_unmet_needs_user_ids') ?? [])));
        }
        if (Arr::has($modelData, 'shopping_assistant_unmet_needs_slack_channel')) {
            data_set($modelData, 'settings.shopping_assistant.unmet_needs_slack_channel', trim((string) Arr::pull($modelData, 'shopping_assistant_unmet_needs_slack_channel')) ?: null);
        }
        if (Arr::has($modelData, 'shopping_assistant_retention_months')) {
            $months = Arr::pull($modelData, 'shopping_assistant_retention_months');
            data_set($modelData, 'settings.shopping_assistant.retention_months', is_numeric($months) ? (int) $months : null);
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

        $group = $this->update($group, $modelData, ['settings']);
        GetShoppingAssistantSettings::forgetGroupName($group->id);

        return $group;
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
            'staff_chat_quick_replies'          => ['sometimes', 'nullable', 'string', 'max:1000'],
            'shopping_assistant_name'           => ['sometimes', 'nullable', 'string', 'max:30'],
            'shopping_assistant_weekly_summary_user_ids'   => ['sometimes', 'nullable', 'array', 'max:20'],
            'shopping_assistant_weekly_summary_user_ids.*' => ['integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)->where('status', true)],
            'shopping_assistant_unmet_needs_user_ids'      => ['sometimes', 'nullable', 'array', 'max:30'],
            'shopping_assistant_unmet_needs_user_ids.*'    => ['integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)->where('status', true)],
            'shopping_assistant_unmet_needs_slack_channel' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Z0-9]+$/'],
            'shopping_assistant_retention_months'          => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'extra_languages'                   => ['sometimes', 'array', 'nullable'],
            'printnode_api_key' => ['sometimes', 'string', 'nullable'],
            'print_by_printnode' => ['sometimes', 'boolean', 'nullable'],
            'timezones'          => ['sometimes', 'array'],
            'timezones.*'        => ['string', 'timezone'],
            'official_stock_valuation_method' => ['sometimes', Rule::in([
                \App\Enums\Inventory\OrgStock\OrgStockValuationMethodEnum::FIFO->value,
                \App\Enums\Inventory\OrgStock\OrgStockValuationMethodEnum::WAC->value,
            ])],
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

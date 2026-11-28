<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 12:45:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Chat\SlackChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A Verba admin sets which organisations get a public channel in their V·Desks list, or hides it from the lists
 * (INI-595). Nothing changes in Slack and nobody loses access: links and search still open it.
 */
class UpdateVerbaChannelSettings
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        $channel = $request->route('slackChannel');

        return $request->user()->authTo(GroupPermissionsEnum::VERBA_ADMIN->value)
            && $channel->group_id === $request->user()->group_id
            && SlackChannel::kept()->whereKey($channel->id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(ActionRequest $request): array
    {
        return [
            'is_hidden_in_lists' => ['sometimes', 'boolean'],
            'organisation_ids'   => ['sometimes', 'array'],
            'organisation_ids.*' => ['integer', 'between:1,32767', Rule::exists('organisations', 'id')->where('group_id', $request->user()->group_id)],
        ];
    }

    /**
     * @param  array{is_hidden_in_lists?: bool, organisation_ids?: array<int, int>}  $settings
     */
    public function handle(SlackChannel $slackChannel, array $settings): SlackChannel
    {
        if (array_key_exists('is_hidden_in_lists', $settings)) {
            $slackChannel->update(['is_hidden_in_lists' => $settings['is_hidden_in_lists']]);
        }
        if (array_key_exists('organisation_ids', $settings)) {
            $before  = $slackChannel->organisations()->orderBy('name')->pluck('name')->all();
            $changes = $slackChannel->organisations()->sync(array_unique($settings['organisation_ids']));
            if (array_filter($changes)) {
                $slackChannel->auditEvent     = 'update';
                $slackChannel->isCustomEvent  = true;
                $slackChannel->auditCustomOld = ['shows_to' => $before ? implode(', ', $before) : 'Everyone'];
                $slackChannel->auditCustomNew = ['shows_to' => implode(', ', $slackChannel->organisations()->orderBy('name')->pluck('name')->all()) ?: 'Everyone'];
                Event::dispatch(new AuditCustom($slackChannel));
                $slackChannel->isCustomEvent  = false;
            }
        }

        return $slackChannel;
    }

    public function asController(SlackChannel $slackChannel, ActionRequest $request): JsonResponse
    {
        $before  = [$slackChannel->is_hidden_in_lists, $slackChannel->organisations()->orderBy('organisations.id')->pluck('organisations.id')->all()];
        $channel = $this->handle($slackChannel, $request->validated());
        $after   = [$channel->is_hidden_in_lists, $channel->organisations()->orderBy('organisations.id')->pluck('organisations.id')->all()];

        return response()->json([
            'is_hidden_in_lists' => $after[0],
            'organisation_ids'   => $after[1],
            ...($before !== $after ? ['last_change' => ['by' => $request->user()->contact_name, 'at' => now()->toIso8601String()]] : []),
        ]);
    }
}

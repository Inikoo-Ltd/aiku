<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A person's own wallpaper for one conversation or Slack channel, private to them; an empty pattern and colour go back to their general one.
 */
class SetStaffChatWallpaper
{
    use AsAction;

    /**
     * @param  array{pattern: string, colour: string}|null  $choice
     *
     * @return array{pattern: string|null, colour: string|null}
     */
    public function forConversation(StaffConversation $conversation, User $user, ?array $choice): array
    {
        $conversation->participants()->updateExistingPivot($user->id, ['wallpaper' => $choice ? json_encode($choice) : null]);

        return StaffChatWallpaper::forConversation($user, $choice);
    }

    /**
     * @param  array{pattern: string, colour: string}|null  $choice
     *
     * @return array{pattern: string|null, colour: string|null}
     */
    public function forSlackChannel(SlackChannel $slackChannel, User $user, ?array $choice): array
    {
        $channels = (array) Arr::get($user->settings, 'chat_wallpaper_channels', []);
        if ($choice) {
            $channels[$slackChannel->id] = $choice;
        } else {
            unset($channels[$slackChannel->id]);
        }
        $user->update(['settings' => [...$user->settings, 'chat_wallpaper_channels' => $channels ?: null]]);

        return StaffChatWallpaper::forSlackChannel($user->refresh(), $slackChannel->id);
    }

    public function rules(): array
    {
        return [
            'conversation_ulid' => ['required_without:slack_channel_id', 'nullable', 'string'],
            'slack_channel_id'  => ['required_without:conversation_ulid', 'nullable', 'integer'],
            'pattern'           => ['present', 'nullable', 'string', Rule::in([StaffChatWallpaper::NONE, ...array_filter(array_keys(StaffChatWallpaper::PATTERNS), fn (string $pattern) => StaffChatWallpaper::isOffered($pattern))])],
            'colour'            => ['present', 'nullable', 'string', Rule::in([StaffChatWallpaper::NONE, ...array_keys(StaffChatWallpaper::COLOURS)])],
        ];
    }

    /**
     * @return array{wallpaper: array{pattern: string|null, colour: string|null}, is_set: bool}
     */
    public function asController(ActionRequest $request): array
    {
        $user   = $request->user();
        $choice = $request->input('pattern') === null && $request->input('colour') === null
            ? null
            : ['pattern' => $request->input('pattern') ?? StaffChatWallpaper::NONE, 'colour' => $request->input('colour') ?? StaffChatWallpaper::NONE];

        if ($request->filled('conversation_ulid')) {
            $conversation = StaffConversation::where('ulid', $request->input('conversation_ulid'))->first();
            if (!$conversation?->hasParticipant($user)) {
                throw ValidationException::withMessages(['conversation_ulid' => __('You are not in this conversation')]);
            }

            return ['wallpaper' => $this->forConversation($conversation, $user, $choice), 'is_set' => (bool) $choice];
        }

        $slackChannel = SlackChannel::find($request->integer('slack_channel_id'));
        if (!$slackChannel?->isReadableBy($user)) {
            throw ValidationException::withMessages(['slack_channel_id' => __('You cannot see this channel')]);
        }

        return ['wallpaper' => $this->forSlackChannel($slackChannel, $user, $choice), 'is_set' => (bool) $choice];
    }
}

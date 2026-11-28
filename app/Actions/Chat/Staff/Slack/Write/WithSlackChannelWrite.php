<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;

/**
 * What creating and changing public channels from aiku share (INI-467): the person's own token only (no stand-in bot),
 * public channels only, Slack's name rule, and Slack's refusals as short sentences. Who may create, rename or archive is
 * Slack's workspace setting; aiku only passes its answer on.
 */
trait WithSlackChannelWrite
{
    use WithSlackMessageWrite;

    /**
     * @return array<int, string>
     */
    public static function nameRules(): array
    {
        return ['required', 'string', 'max:80', 'regex:/^[\p{Ll}\p{Lo}\p{N}_-]+$/u'];
    }

    /**
     * @return array<string, string>
     */
    public function getValidationMessages(): array
    {
        return ['name.regex' => __('Use only lower case letters, numbers, hyphens and underscores, no spaces')];
    }

    protected function mayWriteHere(User $user, SlackChannel $slackChannel): bool
    {
        return !$slackChannel->direct_type && !$slackChannel->is_private && self::mayWrite($user, $slackChannel);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  list<string>  $alsoSuccess
     * @return array<string, mixed>
     */
    protected function callChannel(SlackWriteClient $client, string $method, array $parameters, array $alsoSuccess = [], ?string $fallback = null): array
    {
        try {
            return $client->call($method, $parameters);
        } catch (SlackWriteException $exception) {
            if (in_array($exception->slackError, $alsoSuccess, true)) {
                return ['ok' => true];
            }
            $this->refuse($fallback ?? self::channelSentence($exception->slackError));
        }
    }

    public static function channelSentence(string $code): string
    {
        return match ($code) {
            'name_taken'           => __('Slack already has a channel with this name. Choose another'),
            'invalid_name', 'invalid_name_specials', 'invalid_name_punctuation', 'invalid_name_maxlength', 'invalid_name_required'
                                   => __('Slack does not accept this name. Use lower case letters, numbers, hyphens and underscores'),
            'restricted_action'    => __('Your Slack workspace does not let you do this'),
            'not_authorized', 'user_is_restricted', 'cant_kick_self'
                                   => __('Only the person who created this channel or a Slack admin can do this'),
            'cant_leave_general'   => __('Everyone stays in the general channel of the workspace'),
            'cant_archive_general' => __('The general channel of the workspace cannot be archived'),
            'not_in_channel'       => __('Join this V·Desk first'),
            'cant_invite_self'     => __('You are already in this V·Desk'),
            'user_not_found', 'user_is_bot', 'cant_invite', 'user_disabled'
                                   => __('Slack could not add one of these people'),
            'too_long'             => __('This is too long for Slack'),
            default                => self::sentenceFor($code),
        };
    }

    /**
     * Slack answers topic and purpose changes with the whole channel; an older answer without it still gets the new value.
     *
     * @param  array<string, mixed>  $answer
     */
    protected function keepChannelData(SlackChannel $slackChannel, array $answer, string $key, string $value): void
    {
        $data = Arr::get($answer, 'channel.id') === $slackChannel->slack_id ? (array) $answer['channel'] : (array) $slackChannel->data;
        Arr::set($data, "$key.value", $value);
        $slackChannel->update(['data' => $data]);
    }
}

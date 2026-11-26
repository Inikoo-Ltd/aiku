<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Json;

use App\Actions\Chat\Staff\Slack\FormatSlackMessages;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackMessageVersion;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The earlier wordings of one edited Slack message, newest replacement first, formatted like the stream. A channel the
 * person cannot see is answered with 404, so its messages are not revealed.
 */
class GetSlackMessageVersions
{
    use AsAction;

    public const int LIMIT = 50;

    public function authorize(ActionRequest $request): bool
    {
        abort_unless($request->route('slackChannel')->isReadableBy($request->user()), 404);

        return true;
    }

    /**
     * @return array{versions: list<array{id: int, replaced_at: string|null, nodes: list<array<string, mixed>>}>}
     */
    public function handle(SlackChannel $slackChannel, string $ts): array
    {
        $message = SlackMessage::where('slack_channel_id', $slackChannel->id)->where('ts', $ts)->firstOrFail(['id']);

        $versions = SlackMessageVersion::where('slack_message_id', $message->id)
            ->orderByDesc('replaced_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['id', 'text', 'data', 'replaced_at']);

        $nodes = $versions->isEmpty() ? [] : FormatSlackMessages::make()->handle($versions);

        return [
            'versions' => $versions->map(fn (SlackMessageVersion $version) => [
                'id'          => $version->id,
                'replaced_at' => $version->replaced_at?->toIso8601String(),
                'nodes'       => $nodes[$version->id] ?? [],
            ])->all(),
        ];
    }

    public function rules(): array
    {
        return ['ts' => ['required', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/']];
    }

    /**
     * @return array{versions: list<array{id: int, replaced_at: string|null, nodes: list<array<string, mixed>>}>}
     */
    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        return $this->handle($slackChannel, $request->validated('ts'));
    }
}

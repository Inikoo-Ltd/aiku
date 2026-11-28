<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The Slack workspaces Verba reads (INI-540). The main one is services.slack (tickets, alerts, the private opt-in and DMs
 * live only there) and its channels have slack_team_id null; each of services.slack.other_workspaces has its own app,
 * bot and signing secret, and its channels carry its team id.
 */
final readonly class SlackWorkspace
{
    private function __construct(public ?string $teamId, #[\SensitiveParameter] public string $botToken, #[\SensitiveParameter] public string $signingSecret, public ?string $nameSuffix, public ?string $clientId = null, #[\SensitiveParameter] public ?string $clientSecret = null, public ?string $name = null)
    {
    }

    public static function main(): self
    {
        return new self(null, (string) config('services.slack.notifications.bot_user_oauth_token'), (string) config('services.slack.signing_secret'), null, config('services.slack.client_id'), config('services.slack.client_secret'));
    }

    /**
     * @return array<int, self>
     */
    public static function others(): array
    {
        $mainTeamId = config('services.slack.team_id');
        $mainSecret = config('services.slack.signing_secret');

        return collect(config('services.slack.other_workspaces', []))
            ->filter(fn (array $workspace) => ($workspace['team_id'] ?? null) && ($workspace['bot_token'] ?? null) && ($workspace['signing_secret'] ?? null)
                && $workspace['team_id'] !== $mainTeamId && $workspace['signing_secret'] !== $mainSecret)
            ->map(fn (array $workspace) => new self($workspace['team_id'], $workspace['bot_token'], $workspace['signing_secret'], $workspace['name_suffix'] ?? null, $workspace['client_id'] ?? null, $workspace['client_secret'] ?? null, $workspace['name'] ?? $workspace['team_id']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return [self::main(), ...self::others()];
    }

    public static function forTeam(?string $teamId): ?self
    {
        if ($teamId === null || $teamId === config('services.slack.team_id')) {
            return self::main();
        }

        return collect(self::others())->first(fn (self $workspace) => $workspace->teamId === $teamId);
    }

    public static function isOtherTeam(?string $teamId): bool
    {
        return $teamId !== null && collect(self::others())->contains(fn (self $workspace) => $workspace->teamId === $teamId);
    }

    public static function forChannel(SlackChannel $channel): ?self
    {
        return self::forTeam($channel->slack_team_id);
    }

    public function isMain(): bool
    {
        return $this->teamId === null;
    }

    /**
     * The Slack team id people sign in to: the main one's comes from services.slack.team_id.
     */
    public function slackTeamId(): ?string
    {
        return $this->teamId ?? config('services.slack.team_id');
    }

    /**
     * The person's own linked Slack account in this workspace, never one from another workspace.
     */
    public function personOf(\App\Models\SysAdmin\User $user): ?\App\Models\Chat\SlackUser
    {
        $person = $this->isMain() ? SlackChannel::linkedSlackPerson($user) : \App\Models\Chat\SlackUser::whereIn('slack_id', SlackChannel::linkedSlackIds($user))->where('data->team_id', $this->teamId)->first();

        return $this->isMain() && self::isOtherTeam($person?->data['team_id'] ?? null) ? null : $person;
    }

    public function client(): ?PendingRequest
    {
        return $this->botToken !== '' ? Http::withToken($this->botToken)->baseUrl('https://slack.com/api') : null;
    }

    /**
     * The workspace's name as people see it in Slack, asked from Slack once a day; null when Slack cannot say.
     */
    public function displayName(): ?string
    {
        return $this->name ?? Cache::remember('slack_workspace_name:'.($this->teamId ?? 'main'), now()->addDay(), function () {
            try {
                return $this->client()?->timeout(2)->get('team.info')->json('team.name');
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /**
     * Channels of this workspace: the main one's have no team id.
     */
    public function channels(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->isMain() ? SlackChannel::whereNull('slack_team_id') : SlackChannel::where('slack_team_id', $this->teamId);
    }

    /**
     * A channel this workspace may write to aiku: a new one, or one it already owns. A channel shared between two
     * workspaces is listed and evented by both apps; only the workspace that brought it first keeps it current.
     */
    public function owns(?SlackChannel $channel): bool
    {
        return !$channel || $channel->slack_team_id === $this->teamId;
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Home;

use App\Actions\Chat\Staff\Slack\GetSlackMessageFiles;
use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffMessageStory;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Verba Home: the stories the AI editor picked from the conversations this person is in, and the last people they
 * talked to one to one, so a call is one click away.
 */
class GetStaffHome
{
    use AsAction;

    public const int DAYS = 7;

    public const int STORIES = 40;

    public const int PEOPLE = 5;

    public const int QUICK_MINUTES = 90;

    /**
     * @return array{stories: list<array<string, mixed>>, people: list<array<string, mixed>>, starred: list<array<string, mixed>>}
     */
    public function handle(User $user): array
    {
        $stories = StaffMessageStory::query()
            ->visibleTo($user)
            ->where('happened_at', '>=', now()->subDays(self::DAYS))
            ->where(fn ($query) => $query->whereHas('staffMessage')->orWhereHas('slackMessage'))
            ->where(fn ($query) => $query->where('source', StaffMessageStory::SOURCE_EDITOR)->orWhere('created_at', '>=', now()->subMinutes(self::QUICK_MINUTES)))
            ->with(['author', 'staffMessage', 'slackMessage', 'staffConversation', 'slackChannel'])
            ->orderByRaw('happened_at::date desc')
            ->orderByDesc('importance')
            ->orderByDesc('happened_at')
            ->limit(self::STORIES)
            ->get();

        $slackFiles = GetSlackMessageFiles::make()->handle($stories->pluck('slackMessage')->filter()->values());

        return [
            'stories' => $stories->map(fn (StaffMessageStory $story) => [
                'id'         => $story->id,
                'category'   => $story->category,
                'importance' => $story->importance,
                'headline'   => $story->headline,
                'blurb'      => $story->blurb,
                'author'     => $story->author?->chatName(),
                'avatar'     => $story->author?->imageSources(48, 48),
                'where'      => $story->staffConversation?->name ?? ('#'.$story->slackChannel?->name),
                'image'      => $story->staffMessage?->media_id
                    ? $story->staffMessage->imageSources(1200, 0, 'attachment')
                    : collect($slackFiles[$story->slack_message_id] ?? [])->firstWhere('is_image', true)['url'] ?? null,
                'url'        => $story->staff_conversation_id
                    ? route($story->staffConversation->isVerbration() ? 'grp.chat.staff.groups.show' : 'grp.chat.staff.show', $story->staffConversation->ulid)
                    : route('grp.chat.staff.slack.show', ['slackChannel' => $story->slack_channel_id, 'around' => $story->slackMessage?->ts]),
                'happened_at' => $story->happened_at,
            ])->values()->all(),
            'people'  => $this->recentPeople($user),
            'starred' => $this->starredPeople($user),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentPeople(User $user): array
    {
        return $this->people($user, StaffConversation::query()
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->limit(self::PEOPLE));
    }

    /**
     * The people of the DMs this person starred in Verba (desk_starred "dm-{ulid}", INI-598), by name.
     *
     * @return list<array<string, mixed>>
     */
    private function starredPeople(User $user): array
    {
        $ulids = collect(Arr::get($user->settings, 'desk_starred') ?? [])
            ->filter(fn ($key) => is_string($key) && str_starts_with($key, 'dm-'))
            ->map(fn (string $key) => substr($key, 3))
            ->values()->all();

        return $ulids === [] ? [] : collect($this->people($user, StaffConversation::query()->whereIn('ulid', $ulids)->limit(50)))->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function people(User $user, \Illuminate\Database\Eloquent\Builder $query): array
    {
        return $query
            ->where('type', 'dm')
            ->whereHas('activeParticipants', fn ($query) => $query->where('users.id', $user->id))
            ->with('participants')
            ->get()
            ->map(function (StaffConversation $conversation) use ($user) {
                $other = $conversation->participants->firstWhere('id', '!=', $user->id);

                return $other ? [
                    'conversation_ulid' => $conversation->ulid,
                    'name'              => $other->chatName(),
                    'avatar'            => $other->imageSources(64, 64),
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{stories: list<array<string, mixed>>, people: list<array<string, mixed>>, starred: list<array<string, mixed>>}
     */
    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->user());
    }
}

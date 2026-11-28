<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Chat\Staff\SetStaffChatWallpaper;
use App\Actions\Chat\Staff\StaffChatWallpaper;
use App\Actions\UI\Profile\UpdateProfile;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Only the authenticated user's own Verba preferences, through the same actions as the profile and the Verba screens
 * (UpdateProfile, SetStaffChatWallpaper). A V·Desk or conversation is starred or given a wallpaper only when this user
 * can read it (WithVerbaConversation).
 */
#[Description('Shows or changes the user\'s own Verba preferences: the chat wallpaper (pattern, colour, mode, style), starred V·Desks and conversations, and whether the Verba welcome was seen. Without changes it shows them with the allowed values. With changes and no confirm it previews before and after; with confirm=true and the user\'s request text it saves. wallpaper_conversation sets a wallpaper for one conversation or V·Desk only (pattern and colour none and none takes it back to the general one). star and unstar take ids from verba-tool. Only the user\'s own preferences.')]
class VerbaPreferencesTool extends Tool
{
    use WithVerbaConversation;

    public function handle(Request $request): Response
    {
        $patterns = [StaffChatWallpaper::NONE, ...array_filter(array_keys(StaffChatWallpaper::PATTERNS), fn (string $pattern) => StaffChatWallpaper::isOffered($pattern))];
        $colours  = [StaffChatWallpaper::NONE, ...array_keys(StaffChatWallpaper::COLOURS)];

        $request->validate([
            'pattern'                => ['sometimes', 'string', Rule::in($patterns)],
            'colour'                 => ['sometimes', 'string', Rule::in($colours)],
            'mode'                   => ['sometimes', 'string', Rule::in(StaffChatWallpaper::MODES)],
            'style'                  => ['sometimes', 'string', Rule::in(StaffChatWallpaper::STYLES)],
            'wallpaper_conversation' => ['sometimes', 'string', 'max:40'],
            'star'                   => ['sometimes', 'array', 'max:50'],
            'star.*'                 => ['string', 'max:40'],
            'unstar'                 => ['sometimes', 'array', 'max:50'],
            'unstar.*'               => ['string', 'max:40'],
            'welcome_seen'           => ['sometimes', 'boolean'],
            'confirm'                => ['sometimes', 'boolean'],
            'request_text'           => ['required_if:confirm,true', 'nullable', 'string', 'max:4000'],
        ]);

        /** @var User $user */
        $user   = $request->user();
        $before = $this->current($user);

        $wallpaper = array_filter($request->only(['pattern', 'colour', 'mode', 'style']));
        $hasChange = $wallpaper || $request->hasAny(['star', 'unstar', 'welcome_seen']);
        if (!$hasChange) {
            return Response::json([...$before, 'allowed' => ['pattern' => $patterns, 'colour' => $colours, 'mode' => StaffChatWallpaper::MODES, 'style' => StaffChatWallpaper::STYLES]]);
        }

        $target = null;
        if ($request->filled('wallpaper_conversation')) {
            if (array_diff(array_keys($wallpaper), ['pattern', 'colour'])) {
                return Response::error('Mode and style are general only; a conversation takes a pattern and a colour. Nothing was changed.');
            }
            $target = $this->verbaConversation($user, (string) $request->string('wallpaper_conversation'));
            if (!$target) {
                return Response::error(self::NOT_FOUND.' Nothing was changed.');
            }
        }

        $toStar   = [];
        $unknown  = [];
        foreach (array_merge($request->get('star', []), $request->get('unstar', [])) as $id) {
            if (in_array($id, $request->get('unstar', []), true) && in_array($id, $before['starred'], true)) {
                $toStar[$id] = $id;
                continue;
            }
            $conversation = $this->verbaConversation($user, (string) $id);
            if (!$conversation) {
                $unknown[] = $id;
                continue;
            }
            $toStar[$id] = $conversation instanceof SlackChannel ? 'slack-'.$conversation->id : 'dm-'.$conversation->ulid;
        }
        if ($unknown) {
            return Response::error('Not a conversation or V·Desk this user can read: '.implode(', ', $unknown).'. Nothing was changed.');
        }

        $starred = $before['starred'];
        $starred = array_values(array_unique(array_merge(
            array_diff($starred, array_map(fn ($id) => $toStar[$id], $request->get('unstar', []))),
            array_map(fn ($id) => $toStar[$id], $request->get('star', []))
        )));

        $after = [
            'wallpaper'    => $target ? $before['wallpaper'] : array_merge($before['wallpaper'], $wallpaper),
            'starred'      => $starred,
            'welcome_seen' => $request->has('welcome_seen') ? $request->boolean('welcome_seen') : $before['welcome_seen'],
        ];
        if ($target) {
            $after['wallpaper_of'] = [self::verbaId($target) => array_merge(['pattern' => StaffChatWallpaper::NONE, 'colour' => StaffChatWallpaper::NONE], $wallpaper)];
        }

        if (!$request->boolean('confirm')) {
            return Response::json(['before' => $before, 'after' => $after, 'next' => 'Show the user what changes; call again with confirm=true and their request text once they agree.']);
        }

        if ($target) {
            $choice = ($wallpaper['pattern'] ?? StaffChatWallpaper::NONE) === StaffChatWallpaper::NONE && ($wallpaper['colour'] ?? StaffChatWallpaper::NONE) === StaffChatWallpaper::NONE
                ? null
                : ['pattern' => $wallpaper['pattern'] ?? StaffChatWallpaper::NONE, 'colour' => $wallpaper['colour'] ?? StaffChatWallpaper::NONE];
            $target instanceof StaffConversation
                ? SetStaffChatWallpaper::make()->forConversation($target, $user, $choice)
                : SetStaffChatWallpaper::make()->forSlackChannel($target, $user, $choice);
        }

        UpdateProfile::make()->handle($user, array_filter([
            'chat_wallpaper'     => !$target && $wallpaper ? $wallpaper : null,
            'desk_starred'       => $request->hasAny(['star', 'unstar']) ? $starred : null,
            'verba_welcome_seen' => $request->has('welcome_seen') ? $request->boolean('welcome_seen') : null,
        ], fn ($value) => $value !== null));

        return Response::json(['saved' => true, ...$this->current($user->refresh())]);
    }

    /**
     * @return array{wallpaper: array{pattern: string, colour: string, mode: string, style: string}, starred: array<int, string>, welcome_seen: bool}
     */
    private function current(User $user): array
    {
        return [
            'wallpaper'    => Arr::only(StaffChatWallpaper::stored($user), ['pattern', 'colour', 'mode', 'style']),
            'starred'      => array_values((array) (Arr::get($user->settings, 'desk_starred') ?? [])),
            'welcome_seen' => (bool) Arr::get($user->settings, 'verba_welcome_seen'),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'pattern'                => $schema->string()->description('Wallpaper pattern, e.g. dots, waves or none'),
            'colour'                 => $schema->string()->description('Wallpaper colour, e.g. sand, sage, sky, blush, lavender, butter, mist or none'),
            'mode'                   => $schema->string()->enum(StaffChatWallpaper::MODES)->description('Whose wallpaper a conversation shows: same (the user\'s own everywhere), workplace or person'),
            'style'                  => $schema->string()->enum(StaffChatWallpaper::STYLES)->description('rows (names and text in lines) or bubbles'),
            'wallpaper_conversation' => $schema->string()->description('Id from verba-tool: set pattern and colour for that conversation or V·Desk only'),
            'star'                   => $schema->array()->items($schema->string())->description('Ids from verba-tool to star'),
            'unstar'                 => $schema->array()->items($schema->string())->description('Ids from verba-tool to unstar'),
            'welcome_seen'           => $schema->boolean()->description('Whether the Verba welcome counts as seen (false shows it again)'),
            'confirm'                => $schema->boolean()->description('true to save the change shown in the preview'),
            'request_text'           => $schema->string()->description('The user\'s request, verbatim; required with confirm'),
        ];
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Chat\Staff\Slack\SlackWorkspace;
use App\Actions\Chat\Staff\UpdateVerbaChannelSettings;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Verba Settings (INI-595) for Verba admins: public kept channels only, as on /vx/settings; private channels and DMs are
 * never found here. Saving goes through UpdateVerbaChannelSettings, which records the audit of who changed what.
 */
#[Description('For Verba admins: shows or changes Verba Settings, which public V·Desks (Slack channels) each organisation\'s staff get in their V·Desks list, and which are hidden from every list. It only shapes the lists: nobody loses access, links and search still open every channel, and Slack is never changed. Without channel it lists the public V·Desks (search narrows by name) with shows_to (organisation codes; empty = everyone) and hidden. With channel (its name or slack-<number>) and shows_to and/or hidden and no confirm it previews before and after; with confirm=true and the user\'s request text it saves, recorded in the channel\'s history with the user\'s name. Private channels and DMs are never shown or changed.')]
class VerbaChannelSettingsTool extends Tool
{
    use WithMcpPermissions;

    public function handle(Request $request): Response
    {
        $request->validate([
            'search'       => ['sometimes', 'nullable', 'string', 'max:80'],
            'channel'      => ['sometimes', 'nullable', 'string', 'max:120'],
            'shows_to'     => ['sometimes', 'array', 'max:50'],
            'shows_to.*'   => ['string', 'max:40'],
            'hidden'       => ['sometimes', 'boolean'],
            'confirm'      => ['sometimes', 'boolean'],
            'request_text' => ['required_if:confirm,true', 'nullable', 'string', 'max:4000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        if (!$this->userCan($request, GroupPermissionsEnum::VERBA_ADMIN->value)) {
            return Response::error('Verba Settings are for Verba admins only, and this user is not one. Do not retry. Nothing was changed.');
        }

        $organisations = Organisation::where('group_id', $user->group_id)->orderBy('code')->get(['id', 'code', 'slug', 'name']);
        $kept          = fn () => SlackChannel::kept()->where('group_id', $user->group_id);

        if (!$request->filled('channel')) {
            $search   = strtolower(ltrim((string) $request->get('search'), '#'));
            $channels = $kept()->with('organisations:id,code')
                ->when($search !== '', fn ($query) => $query->whereRaw('lower(name) like ?', ['%'.addcslashes($search, '%_\\').'%']))
                ->orderBy('name')->limit(300)->get();

            return Response::json([
                'organisations' => $organisations->map(fn (Organisation $organisation) => ['code' => $organisation->code, 'name' => $organisation->name])->all(),
                'channels'      => $channels->map(fn (SlackChannel $channel) => $this->row($channel))->all(),
            ]);
        }

        $identifier = strtolower(ltrim((string) $request->string('channel'), '#'));
        if (preg_match('/^slack-(\d{1,18})$/', $identifier, $match)) {
            $channel = $kept()->whereKey((int) $match[1])->first();
        } else {
            $named   = $kept()->get();
            $matches = $named->filter(fn (SlackChannel $channel) => strtolower((string) $channel->name) === $identifier);
            $matches = $matches->isEmpty() ? $named->filter(fn (SlackChannel $channel) => strtolower($channel->getRawOriginal('name')) === $identifier) : $matches;
            if ($matches->count() > 1) {
                return Response::error('More than one public V·Desk is called '.$request->string('channel').' (in different workspaces): use its id, '.$matches->map(fn (SlackChannel $channel) => 'slack-'.$channel->id)->implode(' or ').'. Nothing was changed.');
            }
            $channel = $matches->first();
        }
        if (!$channel) {
            return Response::error('No public V·Desk '.$request->string('channel').'. Call without channel to list them. Nothing was changed.');
        }

        if (!$request->hasAny(['shows_to', 'hidden'])) {
            return Response::json(['channel' => $this->row($channel->load('organisations:id,code'))]);
        }

        $settings = [];
        if ($request->has('hidden')) {
            $settings['is_hidden_in_lists'] = $request->boolean('hidden');
        }
        if ($request->has('shows_to')) {
            $wanted  = collect($request->get('shows_to'))->map(fn ($code) => strtolower(trim((string) $code)))->reject(fn ($code) => in_array($code, ['', 'everyone', 'all'], true));
            $matched = $wanted->mapWithKeys(fn (string $code) => [$code => $organisations->first(fn (Organisation $organisation) => strtolower($organisation->code) === $code || strtolower($organisation->slug) === $code)]);
            $unknown = $matched->filter(fn ($organisation) => $organisation === null)->keys();
            if ($unknown->isNotEmpty()) {
                return Response::error('Unknown organisations: '.$unknown->implode(', ').'. Use the codes from the list. Nothing was changed.');
            }
            $settings['organisation_ids'] = $matched->map(fn (Organisation $organisation) => $organisation->id)->unique()->values()->all();
        }

        $before = $this->row($channel->load('organisations:id,code'));
        $after  = [
            ...$before,
            'hidden'   => $settings['is_hidden_in_lists'] ?? $before['hidden'],
            'shows_to' => array_key_exists('organisation_ids', $settings) ? $this->codes($organisations->whereIn('id', $settings['organisation_ids'])) : $before['shows_to'],
        ];

        if (!$request->boolean('confirm') || $before == $after) {
            return Response::json([
                'before' => $before,
                'after'  => $after,
                'next'   => $before == $after ? 'Nothing to change.' : 'Show the user the change; call again with confirm=true and their request text once they agree.',
            ]);
        }

        Context::addHidden('audit_user', [User::class, $user->id]);
        UpdateVerbaChannelSettings::make()->handle($channel, $settings);

        return Response::json(['saved' => true, 'channel' => $this->row($channel->refresh()->load('organisations:id,code'))]);
    }

    /**
     * @return array{id: string, name: string, workspace: string|null, archived: bool, shows_to: array<int, string>, hidden: bool}
     */
    private function row(SlackChannel $channel): array
    {
        return [
            'id'        => 'slack-'.$channel->id,
            'name'      => (string) $channel->name,
            'workspace' => $channel->slack_team_id ? SlackWorkspace::forTeam($channel->slack_team_id)?->name : null,
            'archived'  => (bool) $channel->is_archived,
            'shows_to'  => $this->codes($channel->organisations),
            'hidden'    => (bool) $channel->is_hidden_in_lists,
        ];
    }

    /**
     * @param  Collection<int, Organisation>  $organisations
     * @return array<int, string>
     */
    private function codes(Collection $organisations): array
    {
        return $organisations->pluck('code')->sort()->values()->all();
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search'       => $schema->string()->description('Part of a channel name, to narrow the list'),
            'channel'      => $schema->string()->description('The public V·Desk to show or change: its name or slack-<number>'),
            'shows_to'     => $schema->array()->items($schema->string())->description('Organisation codes whose staff get it in their list; an empty list (or everyone) = every organisation'),
            'hidden'       => $schema->boolean()->description('true hides it from every list (still reachable by link and search)'),
            'confirm'      => $schema->boolean()->description('true to save the change shown in the preview'),
            'request_text' => $schema->string()->description('The user\'s request, verbatim; required with confirm'),
        ];
    }
}

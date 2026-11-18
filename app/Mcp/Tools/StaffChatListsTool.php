<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\SysAdmin\McpChange\GetMcpChangeSnapshot;
use App\Actions\SysAdmin\Organisation\UpdateOrganisation;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Gated by can_use_mcp_settings on top of the shop or organisation admin permission the same settings page needs in
 * the UI (HELP-4803). Ask CRM is routed by the shop's lists only; the organisation's lists are used for Ask warehouse
 * when a shop has none (GetStaffAudience), so the organisation's CRM lists are refused here.
 */
#[Description('Shows or changes who receives staff chat questions (Ask CRM, Ask warehouse) and their backups, in one shop or in every open shop of an organisation. Without add or remove it shows the four lists (crm, crm_backup, warehouse, warehouse_backup) of each shop and the warehouse lists of the organisation, with usernames and names. With list and add and/or remove (usernames or full names) and no confirm it previews each shop\'s list before and after, with warnings; with confirm=true it saves it. organisation_list=true changes the organisation\'s own warehouse list, used by shops with an empty one; Ask CRM only uses the shop lists. An empty CRM list sends questions to everyone with a customer service role in the shop. Show the user the preview and only confirm after they agreed in their own words, passing their request text. Logged in AI changes and revertible there. Only for users enrolled to change settings through their assistant, in shops or organisations they are admin of.')]
class StaffChatListsTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation'      => ['required', 'string'],
            'shop'              => ['sometimes', 'string'],
            'list'              => ['sometimes', 'string', Rule::in(array_keys(GetMcpChangeSnapshot::STAFF_CHAT_LISTS))],
            'organisation_list' => ['sometimes', 'boolean'],
            'add'               => ['sometimes', 'array', 'max:50'],
            'add.*'             => ['string'],
            'remove'            => ['sometimes', 'array', 'max:50'],
            'remove.*'          => ['string'],
            'confirm'           => ['sometimes', 'boolean'],
            'request_text'      => ['required_if:confirm,true', 'string', 'max:4000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        if (!$user?->can_use_mcp_settings) {
            return Response::error('Changing settings is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
        }

        $identifier   = strtolower((string) $request->string('organisation'));
        $organisation = Organisation::where('group_id', $user->group_id)
            ->where(fn ($query) => $query->whereRaw('lower(code) = ?', [$identifier])->orWhereRaw('lower(slug) = ?', [$identifier]))
            ->first();
        if (!$organisation) {
            return Response::error("'$identifier' is not an organisation.");
        }

        $isOrganisationAdmin = $this->userCan($request, 'org-admin.'.$organisation->id);

        $shops = Shop::where('organisation_id', $organisation->id)
            ->when(
                $request->has('shop'),
                fn ($query) => $query->where(fn ($query) => $query->whereRaw('lower(code) = ?', [strtolower((string) $request->string('shop'))])->orWhereRaw('lower(slug) = ?', [strtolower((string) $request->string('shop'))])),
                fn ($query) => $query->where('state', '!=', ShopStateEnum::CLOSED->value)
            )
            ->orderBy('code')
            ->get();
        if ($request->has('shop') && $shops->isEmpty()) {
            return Response::error("'{$request->string('shop')}' is not a shop of $organisation->code.");
        }

        $notAdmin = $shops->reject(fn (Shop $shop) => $isOrganisationAdmin || $this->userCan($request, 'shop-admin.'.$shop->id));
        $shops    = $shops->diff($notAdmin)->values();
        if ($shops->isEmpty() && !$isOrganisationAdmin) {
            return Response::error("This user is not an admin of $organisation->code or of ".($request->has('shop') ? 'that shop' : 'any of its shops').', which the shop settings need. Nothing was changed.');
        }

        if (!$request->has('add') && !$request->has('remove')) {
            return Response::json([
                'organisation'       => $organisation->code,
                'shops'              => $shops->map(fn (Shop $shop) => ['shop' => $shop->code, 'name' => $shop->name, ...$this->namedLists($shop->settings)])->all(),
                'organisation_lists' => Arr::only($this->namedLists($organisation->settings), ['warehouse', 'warehouse_backup']),
                'not_admin_of'       => $notAdmin->pluck('code')->all(),
                'routing'            => 'Ask CRM goes to the active people on the shop\'s crm list, else its crm_backup list, else everyone with a customer service role in the shop. Ask warehouse uses the shop\'s lists, then the organisation\'s, then dispatch and stock roles.',
            ]);
        }

        if (!$request->has('list')) {
            return Response::error('Say which list to change: crm, crm_backup, warehouse or warehouse_backup. Nothing was changed.');
        }

        $list             = (string) $request->string('list');
        $organisationList = $request->boolean('organisation_list');
        if ($organisationList && str_starts_with($list, 'crm')) {
            return Response::error('Ask CRM only uses the shop lists; the organisation\'s CRM list is not used. Change the shops instead. Nothing was changed.');
        }
        if ($organisationList && !$isOrganisationAdmin) {
            return Response::error("Changing the organisation's list needs organisation admin in $organisation->code, which this user does not have. Nothing was changed.");
        }

        $toAdd    = $this->resolveUsers($user, $request->get('add', []), activeOnly: true);
        $toRemove = $this->resolveUsers($user, $request->get('remove', []), activeOnly: false);
        $unknown  = array_merge($toAdd['unknown'], $toRemove['unknown']);
        if ($unknown) {
            return Response::error('Unknown, inactive or ambiguous staff: '.implode(', ', $unknown).'. Use their aiku username. Nothing was changed.');
        }

        $settingsKey = GetMcpChangeSnapshot::STAFF_CHAT_LISTS[$list];
        $targets     = $organisationList ? collect([$organisation]) : $shops;
        $rows        = $targets->map(function (Shop|Organisation $model) use ($settingsKey, $toAdd, $toRemove, $list) {
            $before = array_values(array_map('intval', Arr::get($model->settings ?? [], 'staff_chat.'.$settingsKey) ?? []));
            $after  = array_values(array_unique(array_merge(
                array_diff($before, $toRemove['users']->pluck('id')->all()),
                $toAdd['users']->pluck('id')->all()
            )));

            return [
                'model'   => $model,
                'before'  => $before,
                'after'   => $after,
                'changed' => $before !== $after,
                'warning' => $after === [] && $before !== [] ? ($list === 'crm' ? 'list becomes empty: Ask CRM falls back to crm_backup, then everyone with a customer service role in the shop' : 'list becomes empty') : null,
            ];
        });

        $usernames = User::whereIn('id', $rows->flatMap(fn (array $row) => array_merge($row['before'], $row['after']))->unique())->pluck('username', 'id');
        $preview   = $rows->map(fn (array $row) => array_filter([
            ($organisationList ? 'organisation' : 'shop') => $row['model']->code,
            'before'  => array_map(fn ($id) => $usernames[$id] ?? '#'.$id, $row['before']),
            'after'   => array_map(fn ($id) => $usernames[$id] ?? '#'.$id, $row['after']),
            'changed' => $row['changed'],
            'warning' => $row['warning'],
        ], fn ($value) => $value !== null))->values()->all();

        $changes = $rows->where('changed', true);
        if (!$request->boolean('confirm') || $changes->isEmpty()) {
            return Response::json([
                'list'          => $list,
                'preview'       => $preview,
                'would_change'  => $changes->count(),
                'not_admin_of'  => $notAdmin->pluck('code')->all(),
                'next'          => $changes->isEmpty() ? 'Nothing to change.' : 'Show this to the user and call again with confirm=true and their request text once they agree.',
            ]);
        }

        $field = 'staff_chat_'.$settingsKey;
        try {
            $this->recordChange(
                $request,
                McpChangeTypeEnum::STAFF_CHAT_LISTS,
                "Staff chat $list list of ".($organisationList ? "organisation $organisation->code" : $changes->map(fn (array $row) => $row['model']->code)->implode(', ')),
                [
                    'shop_ids'         => $organisationList ? [] : $changes->map(fn (array $row) => $row['model']->id)->values()->all(),
                    'organisation_ids' => $organisationList ? [$organisation->id] : [],
                ],
                fn () => DB::transaction(fn () => $changes->each(fn (array $row) => $row['model'] instanceof Shop
                    ? UpdateShop::make()->action($row['model'], [$field => $row['after']])
                    : UpdateOrganisation::make()->action($row['model'], [$field => $row['after']]))),
                ['shops' => $organisationList ? [] : $changes->map(fn (array $row) => $row['model']->code)->values()->all()]
            );
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was changed.');
        }

        return Response::json([
            'changed'       => $changes->count(),
            'list'          => $list,
            'result'        => $preview,
            'change_log_id' => $this->mcpChange?->id,
        ]);
    }

    /**
     * @param  array<int, string>  $identifiers
     * @return array{users: Collection<int, User>, unknown: array<int, string>}
     */
    private function resolveUsers(User $user, array $identifiers, bool $activeOnly): array
    {
        $users   = collect();
        $unknown = [];
        foreach (array_unique(array_map('trim', $identifiers)) as $identifier) {
            $lower   = strtolower($identifier);
            $matches = User::where('group_id', $user->group_id)
                ->when($activeOnly, fn ($query) => $query->where('status', true))
                ->where(fn ($query) => $query->whereRaw('lower(username) = ?', [$lower])->orWhereRaw('lower(contact_name) = ?', [$lower]))
                ->get();
            $exact = $matches->first(fn (User $match) => strtolower($match->username) === $lower);

            if ($exact || $matches->count() === 1) {
                $users->push($exact ?? $matches->first());
            } else {
                $unknown[] = $identifier;
            }
        }

        return ['users' => $users->unique('id')->values(), 'unknown' => $unknown];
    }

    /**
     * @return array<string, array<int, array{username: string, name: string, active: bool}>>
     */
    private function namedLists(?array $settings): array
    {
        $lists = collect(GetMcpChangeSnapshot::STAFF_CHAT_LISTS)->map(fn (string $key) => array_map('intval', Arr::get($settings ?? [], 'staff_chat.'.$key) ?? []));
        $users = User::whereIn('id', $lists->flatten()->unique())->get(['id', 'username', 'contact_name', 'nickname', 'status'])->keyBy('id');

        return $lists->map(fn (array $userIds) => array_values(array_filter(array_map(fn (int $userId) => $users->has($userId) ? [
            'username' => $users[$userId]->username,
            'name'     => $users[$userId]->chatName(),
            'active'   => (bool) $users[$userId]->status,
        ] : null, $userIds))))->all();
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation'      => $schema->string()->description('Organisation code or slug')->required(),
            'shop'              => $schema->string()->description('Shop code or slug; omit for every open shop of the organisation'),
            'list'              => $schema->string()->enum(array_keys(GetMcpChangeSnapshot::STAFF_CHAT_LISTS))->description('The list to change: crm (Ask CRM goes to), crm_backup, warehouse (Ask warehouse goes to) or warehouse_backup; required with add or remove'),
            'organisation_list' => $schema->boolean()->description('Change the organisation\'s own warehouse or warehouse_backup list instead of the shops\''),
            'add'               => $schema->array()->items($schema->string())->description('Usernames or full names of active staff to add'),
            'remove'            => $schema->array()->items($schema->string())->description('Usernames or full names of staff to remove'),
            'confirm'           => $schema->boolean()->description('true to save the change shown in the preview'),
            'request_text'      => $schema->string()->description('The user\'s request, verbatim; required with confirm'),
        ];
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\SysAdmin\GetStaffChatAnalytics;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Usage analytics of the internal staff-to-staff chat (NOT customer chat): messages, people chatting, conversations, messages opened from orders/delivery notes, images, translations, reactions, unread conversations, daily and hourly volume, most chatty users and the pairs of staff who chat most with each other. Counts and metadata only — message contents are never returned. Available to group admins, organisation admins and human resources staff.')]
#[IsReadOnly]
class StaffChatAnalyticsTool extends Tool
{
    use WithMcpPermissions;

    public function shouldRegister(Request $request): bool
    {
        return !$request->user()?->can_use_mcp_sql;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        if (!$this->canSeeStaffChatAnalytics($request)) {
            return Response::error('Permission denied.');
        }

        $insights = GetStaffChatAnalytics::run($request->user()->group, (int) ($request->integer('days') ?: 30));

        return Response::json([
            ...$insights,
            'how_to_read_this' => 'top_users = most chatty people (messages sent). top_pairs = direct-message conversations ranked by message count, members are the two people. context_messages = messages in conversations opened from an order or delivery note (warehouse ↔ customer service). Message bodies are private and are not available through any tool.',
        ]);
    }

    private function canSeeStaffChatAnalytics(Request $request): bool
    {
        if ($this->userCan($request, GroupPermissionsEnum::SYSADMIN_VIEW->value)) {
            return true;
        }

        return Organisation::orderBy('id')->get(['id', 'slug'])
            ->contains(fn (Organisation $organisation) => $this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::ORG_ADMIN->value, $organisation))
                || $this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::HUMAN_RESOURCES_VIEW->value, $organisation)));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('Look-back window in days, default 30, max 365'),
        ];
    }
}

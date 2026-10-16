<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierWechatConversation\Json;

use App\Actions\Procurement\Settings\UpdateProcurementSettings;
use App\Enums\Helpers\Ticket\TicketProjectStatusEnum;
use App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Http\Resources\Procurement\SupplierWechatConversationResource;
use App\Models\Helpers\TicketProject;
use App\Models\Procurement\SupplierWechatConversation;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSupplierWechatConversations
{
    use AsAction;

    /**
     * @return array<int, int>
     */
    public static function organisationIdsFor(User $user): array
    {
        return Organisation::where('group_id', $user->group_id)
            ->where('type', OrganisationTypeEnum::AGENT)
            ->with('country')
            ->get()
            ->filter(fn (Organisation $organisation) => UpdateProcurementSettings::usesWechat($organisation) && $user->authTo("procurement.$organisation->id.view"))
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * Unread is what the supplier side wrote since this person last opened the chat; what staff
     * wrote from WeCom is never news to the panel.
     */
    public function handle(User $user): AnonymousResourceCollection
    {
        $organisationIds = self::organisationIdsFor($user);

        $conversations = SupplierWechatConversation::whereIn('organisation_id', $organisationIds)
            ->whereNotNull('last_message_at')
            ->with(['project', 'organisation'])
            ->select('supplier_wechat_conversations.*')
            ->selectSub(
                DB::table('supplier_messages')
                    ->selectRaw('count(*)')
                    ->whereColumn('supplier_messages.supplier_wechat_conversation_id', 'supplier_wechat_conversations.id')
                    ->where('supplier_messages.direction', SupplierMessageDirectionEnum::INBOUND->value)
                    ->whereRaw('supplier_messages.sent_at > coalesce((select last_read_at from supplier_wechat_conversation_reads where supplier_wechat_conversation_id = supplier_wechat_conversations.id and user_id = ?), \'1970-01-01\')', [$user->id]),
                'unread_count'
            )
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        return SupplierWechatConversationResource::collection($conversations)->additional([
            'meta' => [
                'organisation_ids' => $organisationIds,
                'projects'         => $organisationIds ? $this->projectOptions($user) : [],
            ],
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string, milestones: array<int, array{id: int, name: string}>}>
     */
    private function projectOptions(User $user): array
    {
        return TicketProject::where('group_id', $user->group_id)
            ->whereIn('status', [TicketProjectStatusEnum::ACTIVE, TicketProjectStatusEnum::ON_HOLD])
            ->with('milestones')
            ->orderBy('name')
            ->get()
            ->map(fn (TicketProject $project) => [
                'id'         => $project->id,
                'name'       => $project->name,
                'milestones' => $project->milestones->map(fn ($milestone) => ['id' => $milestone->id, 'name' => $milestone->name])->values()->all(),
            ])
            ->all();
    }

    public function asController(ActionRequest $request): AnonymousResourceCollection
    {
        return $this->handle($request->user());
    }
}

<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-10h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopmentMember;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductDevelopmentAccess
{
    /**
     * @var array<int, array<int, int>>
     */
    private array $agentIdsByUser = [];

    public function isStaff(User $user): bool
    {
        return $user->authTo(['goods.view', 'masters.view', 'compliance.view']);
    }

    /**
     * @return array<int, int>
     */
    public function agentIds(User $user): array
    {
        return $this->agentIdsByUser[$user->id] ??= Agent::query()
            ->where('group_id', $user->group_id)
            ->whereIn('organisation_id', $user->authorisedAgentsOrganisations()->pluck('organisations.id'))
            ->pluck('id')
            ->all();
    }

    public function isMember(User $user, ProductDevelopment $productDevelopment): bool
    {
        return $productDevelopment->members()->where('user_id', $user->id)->exists();
    }

    /**
     * The agents each user works for, for every user of the group: the agent organisations they are
     * authorised for. Someone authorised for every agent at once is a superuser, not agent staff, and
     * is left out.
     *
     * @return array<int, array<int, int>> agent ids by user id
     */
    public function agentIdsByUser(int $groupId): array
    {
        $numberAgents = Agent::where('group_id', $groupId)->count();

        return DB::table('user_has_authorised_models')
            ->join('agents', function ($join) {
                $join->on('agents.organisation_id', '=', 'user_has_authorised_models.model_id')
                    ->where('user_has_authorised_models.model_type', '=', 'Organisation');
            })
            ->where('agents.group_id', $groupId)
            ->get(['user_has_authorised_models.user_id', 'agents.id as agent_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('agent_id')->map(fn ($id) => (int) $id)->unique()->values()->all())
            ->reject(fn (array $agentIds) => $numberAgents > 1 && count($agentIds) >= $numberAgents)
            ->all();
    }

    /**
     * The agent role can only go to someone working for the development's agent.
     *
     * @param array<int, array{user_id?: int|string, role?: string}> $members
     */
    public function agentRoleError(array $members, ?int $agentId, int $groupId): ?string
    {
        $agentUserIds = collect($members)
            ->filter(fn ($member) => ($member['role'] ?? null) === ProductDevelopmentMemberRoleEnum::AGENT->value)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);

        if ($agentUserIds->isEmpty()) {
            return null;
        }

        if (!$agentId) {
            return __('Choose the agent of this development before giving someone the agent role');
        }

        $agentIdsByUser = $this->agentIdsByUser($groupId);
        if ($agentUserIds->contains(fn (int $userId) => !in_array($agentId, $agentIdsByUser[$userId] ?? [], true))) {
            return __('Only someone working for the development\'s agent can take the agent role');
        }

        return null;
    }

    public function isAgentOf(User $user, ProductDevelopment $productDevelopment): bool
    {
        $agentIds = $this->agentIds($user);
        if ($agentIds === []) {
            return false;
        }

        return in_array($productDevelopment->agent_id, $agentIds, true)
            || in_array($productDevelopment->supplier?->agent_id, $agentIds, true);
    }

    public function canView(User $user, ProductDevelopment $productDevelopment): bool
    {
        if ($productDevelopment->group_id !== $user->group_id) {
            return false;
        }

        return $this->isStaff($user) || $this->isAgentOf($user, $productDevelopment) || $this->isMember($user, $productDevelopment);
    }

    public function canSeeAny(User $user): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        return $this->restrictToVisible(ProductDevelopment::query()->where('group_id', $user->group_id), $user)->exists();
    }

    public function canSeeCosts(User $user, ProductDevelopment $productDevelopment): bool
    {
        return $this->isStaff($user) || $this->isAgentOf($user, $productDevelopment);
    }

    /**
     * @return array<int, ProductDevelopmentMemberRoleEnum>
     */
    public function memberRoles(User $user, ProductDevelopment $productDevelopment): array
    {
        return $productDevelopment->members()
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (ProductDevelopmentMember $member) => $member->role)
            ->all();
    }

    public function canEdit(User $user, ProductDevelopment $productDevelopment): bool
    {
        if ($productDevelopment->owner_id === $user->id) {
            return true;
        }

        return in_array(ProductDevelopmentMemberRoleEnum::PURCHASING, $this->memberRoles($user, $productDevelopment), true);
    }

    public function canUpload(User $user, ProductDevelopment $productDevelopment): bool
    {
        return $this->canEdit($user, $productDevelopment)
            || $this->isMember($user, $productDevelopment)
            || $this->isAgentOf($user, $productDevelopment)
            || ($this->isStaff($user) && $user->authTo('goods.edit'));
    }

    /**
     * Roles that may approve a design version or send it back; the owner always can.
     */
    public const array DESIGN_APPROVER_ROLES = [ProductDevelopmentMemberRoleEnum::APPROVER];

    public function canDesign(User $user, ProductDevelopment $productDevelopment): bool
    {
        return $productDevelopment->owner_id === $user->id
            || in_array(ProductDevelopmentMemberRoleEnum::DESIGNER, $this->memberRoles($user, $productDevelopment), true);
    }

    /**
     * Who manages design sections and uploads, replaces or builds artwork: designers, owner and purchasing.
     */
    public function canManageArtwork(User $user, ProductDevelopment $productDevelopment): bool
    {
        return $this->canDesign($user, $productDevelopment) || $this->canEdit($user, $productDevelopment);
    }

    public function canApproveDesign(User $user, ProductDevelopment $productDevelopment): bool
    {
        return $productDevelopment->owner_id === $user->id
            || array_intersect(
                array_map(fn (ProductDevelopmentMemberRoleEnum $role) => $role->value, self::DESIGN_APPROVER_ROLES),
                array_map(fn (ProductDevelopmentMemberRoleEnum $role) => $role->value, $this->memberRoles($user, $productDevelopment))
            ) !== [];
    }

    /**
     * Making samples of an in-house SKU: the production team members, the owner and purchasing.
     */
    public function canManageSamples(User $user, ProductDevelopment $productDevelopment): bool
    {
        return $this->canEdit($user, $productDevelopment)
            || in_array(ProductDevelopmentMemberRoleEnum::PRODUCTION, $this->memberRoles($user, $productDevelopment), true);
    }

    /**
     * Approving a component for purchasing: the owner and purchasing.
     */
    public function canApproveComponents(User $user, ProductDevelopment $productDevelopment): bool
    {
        return $this->canEdit($user, $productDevelopment);
    }

    /**
     * Approving a sample or asking for changes: the same people who approve the design.
     */
    public function canReviewSamples(User $user, ProductDevelopment $productDevelopment): bool
    {
        return $this->canApproveDesign($user, $productDevelopment);
    }

    public function canConfirmPrintLimits(User $user, ProductDevelopment $productDevelopment): bool
    {
        return in_array(ProductDevelopmentMemberRoleEnum::AGENT, $this->memberRoles($user, $productDevelopment), true)
            || $this->isAgentOf($user, $productDevelopment);
    }

    public function canEditChecklistItem(User $user, ProductDevelopmentChecklistItem $checklistItem): bool
    {
        $productDevelopment = $checklistItem->productDevelopment;

        if ($this->canEdit($user, $productDevelopment)) {
            return true;
        }

        if (in_array($checklistItem->role, $this->memberRoles($user, $productDevelopment), true)) {
            return true;
        }

        return $checklistItem->role === ProductDevelopmentMemberRoleEnum::AGENT && $this->isAgentOf($user, $productDevelopment);
    }

    public function restrictToVisible(Builder $query, User $user): Builder
    {
        if ($this->isStaff($user)) {
            return $query;
        }

        $agentIds = $this->agentIds($user);

        return $query->where(function (Builder $query) use ($user, $agentIds) {
            $query->whereExists(fn ($subQuery) => $subQuery->select(DB::raw(1))
                ->from('product_development_members')
                ->whereColumn('product_development_members.product_development_id', 'product_developments.id')
                ->where('product_development_members.user_id', $user->id));

            if ($agentIds !== []) {
                $query->orWhereIn('product_developments.agent_id', $agentIds)
                    ->orWhereExists(fn ($subQuery) => $subQuery->select(DB::raw(1))
                        ->from('suppliers')
                        ->whereColumn('suppliers.id', 'product_developments.supplier_id')
                        ->whereIn('suppliers.agent_id', $agentIds));
            }
        });
    }
}

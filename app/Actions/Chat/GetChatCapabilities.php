<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

class GetChatCapabilities
{
    use AsObject;

    private const DESK_LESS_SCOPES = ['Warehouse', 'Production'];

    /**
     * @return array{is_agent: bool, is_supervisor: bool, can_view: bool, is_read_only: bool}
     */
    public function handle(?User $user): array
    {
        if (!$user) {
            return ['is_agent' => false, 'is_supervisor' => false, 'can_view' => false, 'is_read_only' => true];
        }

        $isAgent      = $this->isAgent($user);
        $isSupervisor = $this->isSupervisor($user);
        $canView      = $isAgent || $isSupervisor || $this->worksAtADesk($user);

        return [
            'is_agent'      => $isAgent,
            'is_supervisor' => $isSupervisor,
            'can_view'      => $canView,
            'is_read_only'  => $canView && !$isAgent,
        ];
    }

    public function isAgent(?User $user): bool
    {
        return (bool) $user?->chatAgent?->shopAssignments()->exists();
    }

    public function isSupervisor(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $permissions = ['sysadmin.view'];

        foreach ($user->authorisedOrganisations()->pluck('organisations.id') as $organisationId) {
            $permissions[] = 'org-supervisor.'.$organisationId;
        }

        foreach ($user->authorisedShops()->pluck('shops.id') as $shopId) {
            $permissions[] = 'supervisor-crm.'.$shopId;
        }

        return $user->authTo($permissions);
    }

    /**
     * Warehouse and production floors have no customer conversations to read, so the chat
     * menu stays off their screens unless they also hold a desk role.
     */
    private function worksAtADesk(User $user): bool
    {
        $scopes = $user->roles()->pluck('name')
            ->map(fn (string $name) => RolesEnum::tryFrom(preg_replace('/-\d+$/', '', $name))?->scope())
            ->filter();

        return $scopes->isEmpty() || $scopes->contains(fn (string $scope) => !in_array($scope, self::DESK_LESS_SCOPES, true));
    }
}

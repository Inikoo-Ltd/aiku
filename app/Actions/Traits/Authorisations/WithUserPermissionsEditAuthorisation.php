<?php

/*
 * Author Louis Perez
 * Created on 02-11-2026-10h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Traits\Authorisations;

use App\Models\SysAdmin\User;

trait WithUserPermissionsEditAuthorisation
{
    protected function canEditUserAsHumanResources(User $editor, User $user): bool
    {
        if ($editor->id === $user->id) {
            return false;
        }

        return $user->employees()->pluck('employees.organisation_id')->contains(
            fn (int $organisationId) => $editor->authTo(["human-resources.$organisationId.edit", "org-supervisor.$organisationId.human-resources"])
        );
    }

    protected function canEditUser(User $editor, User $user): bool
    {
        return $editor->authTo('sysadmin.edit') || $this->canEditUserAsHumanResources($editor, $user);
    }
}

<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Sept 2024 12:01:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\SysAdmin\User\CreateUserAccessToken;
use App\Actions\SysAdmin\User\DeleteUserAccessToken;
use App\Actions\SysAdmin\User\UpdateUserGroupPseudoJobPositions;
use App\Actions\SysAdmin\User\UpdateUserOrganisationPseudoJobPositions;
use App\Actions\SysAdmin\User\UpdateUser;
use Illuminate\Support\Facades\Route;

Route::name('user.')->prefix('user/{user:id}')->group(function () {
    Route::patch('', UpdateUser::class)->name('update');
    Route::post('access-token', CreateUserAccessToken::class)->name('access-token.create');
    Route::delete('access-token', DeleteUserAccessToken::class)->name('access-token.delete');
    Route::patch('group-permissions', UpdateUserGroupPseudoJobPositions::class)->name('group_permissions.update');
    Route::patch('organisation-pseudo-job-positions/{organisation:id}', UpdateUserOrganisationPseudoJobPositions::class)->name('organisation_pseudo_job_positions.update')->withoutScopedBindings();
});

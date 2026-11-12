<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-09h-08m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\postJson;

function createMobileAppUser(Group $group, array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'group_id' => $group->id,
        'password' => 'mobile-app-password',
        'status'   => true,
    ], $attributes));
    $user->stats()->create();

    return $user;
}

function postMobileLogin(array $body, array $headers = ['X-Aiku-App-Key' => 'mobile-test-key']): TestResponse
{
    config(['app.mobile_app_keys' => ['mobile-test-key']]);

    return postJson(route('grp.api.mobile.login'), $body, $headers);
}

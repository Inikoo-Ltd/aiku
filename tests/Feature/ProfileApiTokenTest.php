<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\UI\Profile\DeleteProfileApiToken;
use App\Actions\UI\Profile\StoreProfileApiToken;
use App\Models\SysAdmin\Guest;

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->group = $this->organisation->group;
    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
});

test('user can create an api token', function () {
    $result = StoreProfileApiToken::make()->handle($this->user, 'Claude');

    expect($result['token'])->toBeString()->toContain('|')
        ->and($this->user->tokens()->where('name', 'Claude')->exists())->toBeTrue();
});

test('user can revoke own token', function () {
    StoreProfileApiToken::make()->handle($this->user, 'ToRevoke');
    $token = $this->user->tokens()->where('name', 'ToRevoke')->first();

    $deleted = DeleteProfileApiToken::make()->handle($this->user, $token->id);

    expect($deleted)->toBeTrue()
        ->and($this->user->tokens()->whereKey($token->id)->exists())->toBeFalse();
});

test('user cannot revoke another users token', function () {
    StoreProfileApiToken::make()->handle($this->user, 'NotYours');
    $token = $this->user->tokens()->where('name', 'NotYours')->first();

    $otherGuest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $deleted = DeleteProfileApiToken::make()->handle($otherGuest->getUser(), $token->id);

    expect($deleted)->toBeFalse()
        ->and($this->user->tokens()->whereKey($token->id)->exists())->toBeTrue();
});

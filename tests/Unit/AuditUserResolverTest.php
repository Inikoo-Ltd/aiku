<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Audits\Resolvers\AuditUserResolver;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Context;

test('resolver returns null without auth or context', function () {
    Context::forgetHidden('audit_user');
    expect(AuditUserResolver::resolve())->toBeNull();
});

test('resolver falls back to audit_user context', function () {
    Context::addHidden('audit_user', [User::class, 5]);
    $user = AuditUserResolver::resolve();
    expect($user)->toBeInstanceOf(User::class)
        ->and($user->getAuthIdentifier())->toBe(5)
        ->and($user->getMorphClass())->toBe((new User())->getMorphClass());
    Context::forgetHidden('audit_user');
});

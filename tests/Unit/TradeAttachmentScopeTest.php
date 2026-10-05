<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sept 2026 16:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Actions\Traits\HasBucketAttachment;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;

test('how to use is a public attachment scope with a label', function () {
    expect(TradeAttachmentScopeEnum::publicScopes())->toContain('how_to_use')
        ->and(TradeAttachmentScopeEnum::labels())->toHaveKey('how_to_use');
});

test('public attachment slots match the public scopes and no private scope is public', function () {
    $source = file_get_contents((new \ReflectionClass(HasBucketAttachment::class))->getFileName());
    preg_match("/'public'\s*=>\s*\[(.*?)'private'/s", $source, $publicBlock);
    preg_match_all("/'scope' => '([a-z_]+)'/", $publicBlock[1], $slotScopes);

    expect($slotScopes[1])->toEqualCanonicalizing(TradeAttachmentScopeEnum::publicScopes());

    foreach (TradeAttachmentScopeEnum::publicScopes() as $scope) {
        expect($scope)->not->toEndWith('_private');
    }
});

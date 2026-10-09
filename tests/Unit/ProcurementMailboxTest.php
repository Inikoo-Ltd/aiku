<?php

use App\Models\SysAdmin\Organisation;
use App\Services\Gmail\GmailClient;

test('a procurement mailbox google revoked is not offered for replies or composing', function () {
    $organisation = fn (array $gmail) => (new Organisation())->forceFill(['settings' => ['procurement' => ['gmail' => $gmail]]]);

    expect(GmailClient::procurementMailbox($organisation(['email' => 'buying@shop.test'])))->toBe('buying@shop.test')
        ->and(GmailClient::procurementMailbox($organisation(['email' => 'buying@shop.test', 'revoked_at' => now()->toIso8601String()])))->toBeNull()
        ->and(GmailClient::procurementMailbox($organisation([])))->toBeNull();
});

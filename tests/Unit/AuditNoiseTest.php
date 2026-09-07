<?php

use App\Audits\Redactors\PasswordRedactor;
use App\Models\Dropshipping\Portfolio;

it('skips credential keys from custom audits', function (string $key, bool $skipped) {
    expect((bool) preg_match(PasswordRedactor::SECRET_KEY_PATTERN, $key))->toBe($skipped);
})->with([
    ['settings.credentials.ebay_access_token', true],
    ['settings.credentials.ebay_token_expires_at', true],
    ['settings.pricing.value', false],
]);

it('keeps platform sync flags and touch timestamps out of the portfolio audit allowlist', function () {
    $auditInclude = (new ReflectionClass(Portfolio::class))->getDefaultProperties()['auditInclude'];

    expect($auditInclude)
        ->not->toContain('platform_status', 'exist_in_platform', 'has_valid_platform_product_id', 'number_platform_possible_matches', 'last_added_at')
        ->toContain('status', 'sku');
});

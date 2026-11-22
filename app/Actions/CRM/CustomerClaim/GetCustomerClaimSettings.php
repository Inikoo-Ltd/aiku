<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Models\Catalogue\Shop;

/**
 * A shop's settings for customers' own claims (shops.settings.claims), set by organisation admins
 * on the Claims page. Auto-approve works like a rate limit per customer: a claim up to
 * max_claim_amount is settled with no person while what was auto-approved for that customer in the
 * last window_days, this claim included, stays within their allowance (the larger of
 * allowance_floor and allowance_share % of what they were invoiced in the window) and they made
 * fewer than max_claims_in_window claims in it. Customers claim only where an admin switched
 * self_service on; from then a small automatic allowance applies from day one (Raul, 22 Nov 2026),
 * which admins can change or switch off.
 * framework_since is when the shop moved to claims only, a one-way switch.
 */
class GetCustomerClaimSettings
{
    public const array DEFAULTS = [
        'self_service'          => false,
        'auto_approve'          => true,
        'max_claim_amount'      => 15.0,
        'window_days'           => 90,
        'allowance_floor'       => 30.0,
        'allowance_share'       => 3.0,
        'max_claims_in_window'  => 3,
        'framework_since'       => null,
    ];

    /**
     * @return array{self_service: bool, auto_approve: bool, max_claim_amount: float, window_days: int, allowance_floor: float, allowance_share: float, max_claims_in_window: int, framework: bool, framework_since: ?string}
     */
    public static function for(Shop $shop): array
    {
        $stored = (array) data_get($shop->settings, 'claims', []);

        return [
            'self_service'         => (bool) ($stored['self_service'] ?? self::DEFAULTS['self_service']),
            'auto_approve'         => (bool) ($stored['auto_approve'] ?? self::DEFAULTS['auto_approve']),
            'max_claim_amount'     => (float) ($stored['max_claim_amount'] ?? self::DEFAULTS['max_claim_amount']),
            'window_days'          => (int) ($stored['window_days'] ?? self::DEFAULTS['window_days']),
            'allowance_floor'      => (float) ($stored['allowance_floor'] ?? self::DEFAULTS['allowance_floor']),
            'allowance_share'      => (float) ($stored['allowance_share'] ?? self::DEFAULTS['allowance_share']),
            'max_claims_in_window' => (int) ($stored['max_claims_in_window'] ?? self::DEFAULTS['max_claims_in_window']),
            'framework'            => !empty($stored['framework_since']),
            'framework_since'      => $stored['framework_since'] ?? null,
        ];
    }

    /**
     * The shop works through claims only (step 2): + Claim replaces + Replacement on orders and
     * Create refund on invoices. Switched on once by an organisation admin and never off from the
     * screen; only engineers can undo it (claims:undo-framework).
     */
    public static function usesFramework(Shop $shop): bool
    {
        return self::for($shop)['framework'];
    }
}

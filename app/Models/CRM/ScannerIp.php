<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Model;

/**
 * A reputation list of mail-scanner IPs, earned rather than configured. One burst is an anecdote -
 * a corporate office can host both an appliance and real readers - but the same IP detonating
 * several different campaigns is an appliance, and once listed its clicks count as scanner
 * immediately, which is the only way to catch a single-link email where no burst can ever form.
 * Listings decay: vendors rotate ranges, so an IP silent for the TTL falls off the list.
 *
 * @property int $id
 * @property string $ip
 * @property array<array-key, string> $campaign_refs
 * @property \Illuminate\Support\Carbon $last_burst_at
 */
class ScannerIp extends Model
{
    public const LISTING_CAMPAIGN_THRESHOLD = 3;
    public const LISTING_TTL_DAYS           = 90;
    public const MAX_STORED_CAMPAIGN_REFS   = 20;

    protected $guarded = [];

    protected $casts = [
        'campaign_refs' => 'array',
        'last_burst_at' => 'datetime',
    ];

    public static function recordBurst(?string $ip, ?string $campaignRef): void
    {
        if (!$ip || !$campaignRef) {
            return;
        }

        $scannerIp = self::firstOrCreate(
            ['ip' => $ip],
            ['campaign_refs' => [], 'last_burst_at' => now()]
        );

        $campaignRefs = $scannerIp->campaign_refs;
        if (!in_array($campaignRef, $campaignRefs, true)) {
            $campaignRefs[] = $campaignRef;
            $campaignRefs   = array_slice($campaignRefs, -self::MAX_STORED_CAMPAIGN_REFS);
        }

        $scannerIp->update([
            'campaign_refs' => $campaignRefs,
            'last_burst_at' => now(),
        ]);
    }

    public static function isListed(?string $ip): bool
    {
        if (!$ip) {
            return false;
        }

        $scannerIp = self::where('ip', $ip)->first();

        return $scannerIp
            && count($scannerIp->campaign_refs) >= self::LISTING_CAMPAIGN_THRESHOLD
            && $scannerIp->last_burst_at->gt(now()->subDays(self::LISTING_TTL_DAYS));
    }
}

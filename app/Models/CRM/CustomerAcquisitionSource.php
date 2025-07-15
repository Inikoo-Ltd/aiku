<?php

/*
 * Author: Peter Murphy <peter@grp.org>
 * Created: Mon, 15 Jul 2025 10:00:00 GMT
 * Copyright (c) 2025, GRP
 */

namespace App\Models\CRM;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\CRM\CustomerAcquisitionSource
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $customer_id
 * @property string $platform
 * @property string|null $tracking_id
 * @property array|null $utm_parameters
 * @property string|null $referrer_url
 * @property string|null $landing_page
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $captured_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property int $attribution_window_days
 * @property bool $is_active
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CRM\OfflineConversion> $offlineConversions
 */
class CustomerAcquisitionSource extends Model
{
    use HasFactory;
    use InOrganisation;

    protected $guarded = [];

    protected $casts = [
        'utm_parameters' => 'array',
        'data' => 'array',
        'captured_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Platform-specific attribution windows (in days)
    public const ATTRIBUTION_WINDOWS = [
        'google_ads' => 90,
        'meta_ads' => 28,
        'microsoft_ads' => 90,
        'tiktok_ads' => 28,
        'linkedin_ads' => 30,
        'pinterest_ads' => 30,
        'snapchat_ads' => 28,
        'twitter_ads' => 30,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function offlineConversions(): HasMany
    {
        return $this->hasMany(OfflineConversion::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('expires_at', '>', now());
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function scopeWithinAttributionWindow(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function isWithinAttributionWindow(): bool
    {
        return $this->expires_at > now() && $this->is_active;
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Calculate expiration date based on platform attribution window
     */
    public static function calculateExpirationDate(string $platform, \Carbon\Carbon $capturedAt = null): \Carbon\Carbon
    {
        $capturedAt = $capturedAt ?? now();
        $attributionDays = self::ATTRIBUTION_WINDOWS[$platform] ?? 30; // Default 30 days

        return $capturedAt->addDays($attributionDays);
    }

    /**
     * Get attribution window days for platform
     */
    public static function getAttributionWindowDays(string $platform): int
    {
        return self::ATTRIBUTION_WINDOWS[$platform] ?? 30;
    }
}

<?php

namespace App\Models\CRM;

use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerTrafficAd extends Model
{
    use HasFactory;
    use InShop;

    protected $guarded = [];

    protected $casts = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function trafficSource(): BelongsTo
    {
        return $this->belongsTo(TrafficSource::class);
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

    public function scopeWithinAttributionWindow(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function isWithinAttributionWindow(): bool
    {
        return $this->expires_at > now() && $this->is_active;
    }
}

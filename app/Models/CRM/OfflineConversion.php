<?php

/*
 * Author: Peter Murphy <peter@grp.org>
 * Created: Mon, 15 Jul 2025 10:00:00 GMT
 * Copyright (c) 2025, GRP
 */

namespace App\Models\CRM;

use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\CRM\OfflineConversion
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $customer_id
 * @property int|null $customer_acquisition_source_id
 * @property int|null $order_id
 * @property string|null $external_order_id
 * @property string|null $platform_source
 * @property float $revenue
 * @property string $currency
 * @property float|null $grp_exchange
 * @property float|null $org_exchange
 * @property string|null $advertising_platform
 * @property string|null $tracking_id
 * @property \Illuminate\Support\Carbon $conversion_date
 * @property \Illuminate\Support\Carbon|null $attribution_date
 * @property bool $within_attribution_window
 * @property bool $uploaded_to_platform
 * @property \Illuminate\Support\Carbon|null $uploaded_at
 * @property array|null $upload_response
 * @property string $upload_status
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read CustomerAcquisitionSource|null $customerAcquisitionSource
 * @property-read Group $group
 * @property-read Order|null $order
 * @property-read Organisation $organisation
 */
class OfflineConversion extends Model
{
    use HasFactory;
    use InOrganisation;

    protected $guarded = [];

    protected $casts = [
        'revenue' => 'decimal:4',
        'grp_exchange' => 'decimal:4',
        'org_exchange' => 'decimal:4',
        'conversion_date' => 'datetime',
        'attribution_date' => 'datetime',
        'uploaded_at' => 'datetime',
        'within_attribution_window' => 'boolean',
        'uploaded_to_platform' => 'boolean',
        'upload_response' => 'array',
        'data' => 'array',
    ];

    public const UPLOAD_STATUS_PENDING = 'pending';
    public const UPLOAD_STATUS_UPLOADED = 'uploaded';
    public const UPLOAD_STATUS_FAILED = 'failed';
    public const UPLOAD_STATUS_SKIPPED = 'skipped';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerAcquisitionSource(): BelongsTo
    {
        return $this->belongsTo(CustomerAcquisitionSource::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopePendingUpload(Builder $query): Builder
    {
        return $query->where('upload_status', self::UPLOAD_STATUS_PENDING)
            ->where('uploaded_to_platform', false)
            ->where('within_attribution_window', true);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('advertising_platform', $platform);
    }

    public function scopeWithinAttributionWindow(Builder $query): Builder
    {
        return $query->where('within_attribution_window', true);
    }

    public function markAsUploaded(array $response = null): void
    {
        $this->update([
            'uploaded_to_platform' => true,
            'uploaded_at' => now(),
            'upload_status' => self::UPLOAD_STATUS_UPLOADED,
            'upload_response' => $response,
        ]);
    }

    public function markUploadFailed(array $response = null): void
    {
        $this->update([
            'upload_status' => self::UPLOAD_STATUS_FAILED,
            'upload_response' => $response,
        ]);
    }

    public function markUploadSkipped(string $reason = null): void
    {
        $this->update([
            'upload_status' => self::UPLOAD_STATUS_SKIPPED,
            'upload_response' => $reason ? ['reason' => $reason] : null,
        ]);
    }

    /**
     * Check if this conversion is eligible for upload to advertising platform
     */
    public function isEligibleForUpload(): bool
    {
        return $this->within_attribution_window
            && !$this->uploaded_to_platform
            && $this->upload_status === self::UPLOAD_STATUS_PENDING
            && !empty($this->advertising_platform)
            && !empty($this->tracking_id);
    }
}

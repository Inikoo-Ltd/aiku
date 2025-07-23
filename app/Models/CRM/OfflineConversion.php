<?php


namespace App\Models\CRM;

use App\Enums\CRM\OfflineConversion\OfflineConversionUploadStatusEnum;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InOrganisation;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineConversion extends Model
{
    use HasFactory;
    use InShop;

    protected $guarded = [];

    protected $casts = [
        'revenue' => 'decimal:4',
        'conversion_date' => 'datetime',
        'attribution_date' => 'datetime',
        'uploaded_at' => 'datetime',
        'within_attribution_window' => 'boolean',
        'uploaded_to_platform' => 'boolean',
        'upload_response' => 'array',
        'upload_status' => OfflineConversionUploadStatusEnum::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function CustomerTrafficAd(): BelongsTo
    {
        return $this->belongsTo(CustomerTrafficAd::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopePendingUpload(Builder $query): Builder
    {
        return $query->where('upload_status', OfflineConversionUploadStatusEnum::PENDING)
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

    public function markAsUploaded(?array $response = null): void
    {
        $this->update([
            'uploaded_to_platform' => true,
            'uploaded_at' => now(),
            'upload_status' => OfflineConversionUploadStatusEnum::UPLOADED,
            'upload_response' => $response,
        ]);
    }

    public function markUploadFailed(?array $response = null): void
    {
        $this->update([
            'upload_status' => OfflineConversionUploadStatusEnum::FAILED,
            'upload_response' => $response,
        ]);
    }

    public function markUploadSkipped(?string $reason = null): void
    {
        $this->update([
            'upload_status' => OfflineConversionUploadStatusEnum::SKIPPED,
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
            && $this->upload_status === OfflineConversionUploadStatusEnum::PENDING
            && !empty($this->advertising_platform)
            && !empty($this->tracking_id);
    }
}

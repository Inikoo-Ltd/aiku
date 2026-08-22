<?php

namespace App\Models\Web;

use App\Enums\Announcement\AnnouncementStateEnum;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Models\Helpers\Deployment;
use App\Models\Helpers\Snapshot;
use App\Models\Traits\HasImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * App\Models\Web\Announcement
 *
 * @property int $id
 * @property int|null $customer_id
 * @property string|null $code
 * @property string $ulid
 * @property string $name
 * @property string|null $icon
 * @property array<array-key, mixed> $fields
 * @property array<array-key, mixed> $container_properties
 * @property int|null $website_id
 * @property int|null $unpublished_snapshot_id
 * @property int|null $live_snapshot_id
 * @property \Illuminate\Support\Carbon|null $ready_at
 * @property \Illuminate\Support\Carbon|null $live_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property string|null $published_checksum
 * @property AnnouncementStateEnum $state
 * @property bool $is_dirty
 * @property \Illuminate\Support\Carbon|null $schedule_at
 * @property \Illuminate\Support\Carbon|null $schedule_finish_at
 * @property AnnouncementStatusEnum $status
 * @property int|null $paused_by_announcement_id
 * @property \Illuminate\Support\Carbon|null $paused_until
 * @property array<array-key, mixed> $settings
 * @property string|null $compiled_layout
 * @property string|null $text
 * @property string|null $published_message
 * @property array<array-key, mixed>|null $published_settings
 * @property string|null $published_fields
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $group_id
 * @property int|null $organisation_id
 * @property string|null $template_code
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Deployment> $deployments
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read Snapshot|null $liveSnapshot
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Snapshot> $snapshots
 * @property-read Snapshot|null $unpublishedSnapshot
 * @property-read \App\Models\Web\Website|null $website
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement query()
 * @mixin \Eloquent
 */
class Announcement extends Model implements HasMedia
{
    use HasFactory;
    use HasImage;

    protected $guarded = [];

    protected $casts   = [
        "container_properties" => "array",
        "fields"               => "array",
        "settings"             => "array",
        "published_settings"   => "array",
        "live_at"              => "datetime",
        "ready_at"              => "datetime",
        "closed_at"            => "datetime",
        "schedule_at"          => "datetime",
        "schedule_finish_at"   => "datetime",
        "paused_until"         => "datetime",
        'state'                => AnnouncementStateEnum::class,
        'status'               => AnnouncementStatusEnum::class
    ];

    protected $attributes = [
        'container_properties'   => '{}',
        'fields'                 => '{}',
        'settings'               => '{}',
        'published_settings'     => '{}'
    ];

    public function getPosition(): string
    {
        return $this->settings['position'] ?? 'top-bar';
    }

    /**
     * Active announcements of the same website sharing this position whose live window overlaps
     * the given one. A null $until means the window never ends.
     */
    public function scopeClashingWith(Builder $query, int $websiteId, string $position, Carbon $from, ?Carbon $until): Builder
    {
        $query
            ->where('website_id', $websiteId)
            ->where('status', AnnouncementStatusEnum::ACTIVE)
            ->whereRaw("coalesce(settings->>'position', 'top-bar') = ?", [$position])
            ->where(
                fn ($query) => $query
                    ->whereNull('schedule_finish_at')
                    ->orWhere('schedule_finish_at', '>', $from)
            );

        if ($until) {
            $query->whereRaw('coalesce(schedule_at, live_at, created_at) < ?', [$until]);
        }

        return $query;
    }

    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'paused_by_announcement_id');
    }

    public function extractSettings(array $data): array
    {
        $showPages = [];
        $hidePages = [];

        if (blank($data)) {
            return [
                'show_pages' => [],
                'hide_pages' => [],
            ];
        }

        if ($data['target_pages']['type'] === 'all') {
            $showPages = ['all'];
        } elseif ($data['target_pages']['type'] === 'specific') {
            foreach ($data['target_pages']['specific'] as $page) {
                if ($page['will'] === 'show') {
                    $showPages[] = $page['url'];
                } elseif ($page['will'] === 'hide') {
                    $hidePages[] = $page['url'];
                }
            }
        }

        return [
            'show_pages' => $showPages,
            'hide_pages' => $hidePages,
        ];
    }

    public function snapshots(): MorphMany
    {
        return $this->morphMany(Snapshot::class, 'parent');
    }
    public function unpublishedSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'unpublished_snapshot_id');
    }

    public function liveSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'live_snapshot_id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }

    public function deployments(): MorphMany
    {
        return $this->morphMany(Deployment::class, 'model');
    }
}

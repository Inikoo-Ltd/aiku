<?php

namespace App\Models;

use App\Enums\Announcement\AnnouncementStateEnum;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Models\Helpers\Deployment;
use App\Models\Helpers\Snapshot;
use App\Models\Traits\HasImage;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;

/**
 * App\Models\Announcement
 *
 * @property AnnouncementStateEnum $state
 * @property AnnouncementStatusEnum $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Deployment> $deployments
 * @property-read Snapshot|null $liveSnapshot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Snapshot> $snapshots
 * @property-read Snapshot|null $unpublishedSnapshot
 * @property-read Website|null $website
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
        'state'                => AnnouncementStateEnum::class,
        'status'               => AnnouncementStatusEnum::class
    ];

    protected $attributes = [
        'container_properties'   => '{}',
        'fields'                 => '{}',
        'settings'               => '{}',
        'published_settings'     => '{}'
    ];

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

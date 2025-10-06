<?php

namespace App\Models;

use App\Enums\Announcement\AnnouncementStateEnum;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Models\Helpers\Deployment;
use App\Models\Helpers\Snapshot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * App\Models\Announcement
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string|null $icon
 * @property array $fields
 * @property array $container_properties
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $unpublished_snapshot_id
 * @property int|null $live_snapshot_id
 * @property string|null $ready_at
 * @property \Illuminate\Support\Carbon|null $live_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property string|null $published_checksum
 * @property AnnouncementStateEnum $state
 * @property bool $is_dirty
 * @property int|null $customer_id
 * @property \Illuminate\Support\Carbon|null $schedule_at
 * @property \Illuminate\Support\Carbon|null $schedule_finish_at
 * @property AnnouncementStatusEnum $status
 * @property array $settings
 * @property string|null $template_code
 * @property int|null $portfolio_website_id
 * @property string|null $compiled_layout
 * @property string|null $text
 * @property string|null $published_message
 * @property array|null $published_settings
 * @property mixed|null $published_fields
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Deployment> $deployments
 * @property-read int|null $deployments_count
 * @property-read Snapshot|null $liveSnapshot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Snapshot> $snapshots
 * @property-read int|null $snapshots_count
 * @property-read Snapshot|null $unpublishedSnapshot
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement query()
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereClosedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereCompiledLayout($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereContainerProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereIsDirty($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereLiveAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereLiveSnapshotId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement wherePortfolioWebsiteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement wherePublishedChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement wherePublishedFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement wherePublishedMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement wherePublishedSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereReadyAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereScheduleAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereScheduleFinishAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereTemplateCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereUnpublishedSnapshotId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Announcement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Announcement extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts   = [
        "container_properties" => "array",
        "fields"               => "array",
        "settings"             => "array",
        "published_settings"   => "array",
        "live_at"              => "datetime",
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

    public function deployments(): MorphMany
    {
        return $this->morphMany(Deployment::class, 'model');
    }
}

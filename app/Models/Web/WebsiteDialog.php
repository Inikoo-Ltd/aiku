<?php

namespace App\Models\Web;

use App\Enums\Web\WebsiteDialog\WebsiteDialogDisplayFrequencyEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStateEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStatusEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogTriggerEnum;
use App\Models\Helpers\Deployment;
use App\Models\Helpers\Snapshot;
use App\Models\Traits\HasImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * App\Models\Web\WebsiteDialog
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $website_id
 * @property string $ulid
 * @property string $name
 * @property string|null $template_code
 * @property string|null $component
 * @property array<array-key, mixed> $fields
 * @property array<array-key, mixed> $container_properties
 * @property array<array-key, mixed> $settings
 * @property array<array-key, mixed>|null $published_layout
 * @property int|null $unpublished_snapshot_id
 * @property int|null $live_snapshot_id
 * @property string|null $published_checksum
 * @property string|null $published_message
 * @property WebsiteDialogStateEnum $state
 * @property WebsiteDialogStatusEnum $status
 * @property bool $is_dirty
 * @property Carbon|null $ready_at
 * @property Carbon|null $live_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $schedule_at
 * @property Carbon|null $schedule_finish_at
 * @property int|null $paused_by_website_dialog_id
 * @property Carbon|null $paused_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Deployment> $deployments
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read Snapshot|null $liveSnapshot
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WebsiteDialogDismissal> $dismissals
 * @property-read WebsiteDialog|null $pausedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WebsiteDialog> $pausedWebsiteDialogs
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Snapshot> $snapshots
 * @property-read Snapshot|null $unpublishedSnapshot
 * @property-read Website $website
 * @method static Builder<static>|WebsiteDialog newModelQuery()
 * @method static Builder<static>|WebsiteDialog newQuery()
 * @method static Builder<static>|WebsiteDialog query()
 * @mixin \Eloquent
 */
class WebsiteDialog extends Model implements HasMedia
{
    use HasImage;

    protected $guarded = [];

    protected $attributes = [
        'fields'               => '{}',
        'container_properties' => '{}',
        'settings'             => '{}',
    ];

    protected function casts(): array
    {
        return [
            'fields'               => 'array',
            'container_properties' => 'array',
            'settings'             => 'array',
            'published_layout'     => 'array',
            'is_dirty'             => 'boolean',
            'ready_at'             => 'datetime',
            'live_at'              => 'datetime',
            'closed_at'            => 'datetime',
            'schedule_at'          => 'datetime',
            'schedule_finish_at'   => 'datetime',
            'paused_until'         => 'datetime',
            'state'                => WebsiteDialogStateEnum::class,
            'status'               => WebsiteDialogStatusEnum::class,
        ];
    }

    public function getDisplayFrequency(): string
    {
        $settings = $this->published_layout['settings'] ?? $this->settings;

        return Arr::get($settings, 'display_frequency', WebsiteDialogDisplayFrequencyEnum::ONCE_PER_SESSION->value);
    }

    public function getTrigger(): string
    {
        $settings = $this->published_layout['settings'] ?? $this->settings;

        return Arr::get($settings, 'trigger', WebsiteDialogTriggerEnum::AUTOMATIC->value);
    }

    public function getDraftTrigger(): string
    {
        return Arr::get($this->settings, 'trigger', WebsiteDialogTriggerEnum::AUTOMATIC->value);
    }

    /**
     * Dialogs of the same website that pop up by themselves during a window overlapping the given
     * one, as a website only ever pops up one dialog at a time: the active ones and the published
     * ones waiting for their start date. Dialogs opened by a button never clash. A null $until
     * means the window never ends.
     */
    public function scopeClashingWith(Builder $query, int $websiteId, Carbon $from, ?Carbon $until): Builder
    {
        $query
            ->where('website_id', $websiteId)
            ->where(
                fn ($query) => $query
                    ->where('status', WebsiteDialogStatusEnum::ACTIVE)
                    ->orWhere(fn ($query) => $query->waitingForStart())
            )
            ->whereRaw("coalesce(published_layout->'settings'->>'trigger', ?) = ?", [WebsiteDialogTriggerEnum::AUTOMATIC->value, WebsiteDialogTriggerEnum::AUTOMATIC->value])
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

    public function scopeWaitingForStart(Builder $query): Builder
    {
        return $query
            ->where('status', WebsiteDialogStatusEnum::INACTIVE)
            ->where('state', WebsiteDialogStateEnum::READY)
            ->whereNull('paused_by_website_dialog_id')
            ->where('live_at', '>', now());
    }

    public function isWaitingForStart(): bool
    {
        return $this->status === WebsiteDialogStatusEnum::INACTIVE
            && $this->state === WebsiteDialogStateEnum::READY
            && !$this->paused_by_website_dialog_id
            && $this->live_at?->isFuture();
    }

    /**
     * The status the dialog's own dates call for right now, ignoring any pause.
     */
    public function statusForOwnDates(): WebsiteDialogStatusEnum
    {
        return ($this->live_at?->isFuture() || $this->schedule_finish_at?->isPast())
            ? WebsiteDialogStatusEnum::INACTIVE
            : WebsiteDialogStatusEnum::ACTIVE;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{show_pages: array<int, string>, hide_pages: array<int, string>}
     */
    public function extractTargetPages(array $settings): array
    {
        $showPages = [];
        $hidePages = [];

        $targetType = Arr::get($settings, 'target_pages.type');

        if ($targetType === 'all') {
            $showPages = ['all'];
        } elseif ($targetType === 'specific') {
            foreach (Arr::get($settings, 'target_pages.specific', []) as $page) {
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

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(WebsiteDialog::class, 'paused_by_website_dialog_id');
    }

    public function pausedWebsiteDialogs(): HasMany
    {
        return $this->hasMany(WebsiteDialog::class, 'paused_by_website_dialog_id');
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(WebsiteDialogDismissal::class);
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

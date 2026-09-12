<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Chat\StaffConversation;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasTicketImages;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $group_id
 * @property int|null $organisation_id
 * @property int|null $shop_id
 * @property int|null $customer_id
 * @property TicketTypeEnum $type
 * @property TicketKindEnum|null $kind
 * @property TicketModuleEnum|null $module
 * @property array<int, string> $tags
 * @property bool $is_confidential
 * @property int $number
 * @property string $reference
 * @property TicketStatusEnum $status
 * @property ChatPriorityEnum $priority
 * @property string $subject
 * @property string|null $description
 * @property string|null $reporter_type
 * @property int|null $reporter_id
 * @property int|null $assignee_id
 * @property \Illuminate\Support\Carbon|null $waiting_until
 * @property string|null $model_type
 * @property int|null $model_id
 * @property array<array-key, mixed> $data
 * @property int|null $rating
 * @property string|null $rating_comment
 * @property \Illuminate\Support\Carbon|null $rated_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $assigned_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $waiting_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read User|null $assignee
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketComment> $comments
 * @property-read Customer|null $customer
 * @property-read Model|\Eloquent|null $model
 * @property-read Model|\Eloquent|null $reporter
 * @mixin \Eloquent
 */
class Ticket extends Model implements Auditable, HasMedia
{
    use SoftDeletes;
    use HasHistory;
    use InShop;
    use InteractsWithMedia;
    use HasTicketImages;

    protected $guarded = [];

    protected $attributes = [
        'data'     => '{}',
        'tags'     => '[]',
        'status'   => TicketStatusEnum::OPEN,
        'priority' => ChatPriorityEnum::NORMAL,
    ];

    protected array $auditInclude = [
        'status',
        'priority',
        'assignee_id',
        'subject',
        'module',
        'tags',
        'is_confidential',
    ];

    protected function casts(): array
    {
        return [
            'type'        => TicketTypeEnum::class,
            'kind'        => TicketKindEnum::class,
            'module'      => TicketModuleEnum::class,
            'status'      => TicketStatusEnum::class,
            'priority'    => ChatPriorityEnum::class,
            'data'        => 'array',
            'waiting_until' => 'datetime',
            'tags'        => 'array',
            'is_confidential' => 'boolean',
            'rated_at'    => 'datetime',
            'assigned_at' => 'datetime',
            'started_at'  => 'datetime',
            'waiting_at'  => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at'   => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function reporter(): MorphTo
    {
        return $this->morphTo();
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public const array PRESET_TAGS = ['not a bug', 'lack of training', 'not enough info', 'duplicate', 'user error', 'data fix', 'wont fix'];

    public static function knownTags(int $groupId): array
    {
        $used = DB::table('tickets')->where('group_id', $groupId)->selectRaw('distinct jsonb_array_elements_text(tags) as tag')->pluck('tag')->all();

        return array_values(array_unique(array_merge(self::PRESET_TAGS, $used)));
    }

    public static function canBeManagedBy(?User $user): bool
    {
        return $user !== null && $user->authTo('help-desk.resolve');
    }

    public static function canBeAssignedBy(?User $user): bool
    {
        return $user !== null && $user->authTo('help-desk.assign');
    }

    public function defaultWaitingHours(): int
    {
        return $this->reporter_type === 'User' ? 72 : 14 * 24;
    }

    public function isReportedBy(?User $user): bool
    {
        return $user !== null && $this->reporter_type === 'User' && $this->reporter_id === $user->id;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('group-admin')) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->where('tickets.is_confidential', false)
            ->orWhere('tickets.assignee_id', $user->id)
            ->orWhere(fn (Builder $query) => $query->where('tickets.reporter_type', 'User')->where('tickets.reporter_id', $user->id)));
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->whereKey($this->id)->visibleTo($user)->exists();
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Ticket::class, 'model_id')->where('model_type', 'Ticket');
    }

    public function staffConversation(): MorphOne
    {
        return $this->morphOne(StaffConversation::class, 'context');
    }
}

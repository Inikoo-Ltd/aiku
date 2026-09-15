<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Models\CRM\WebUser;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasTicketImages;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'qa_status',
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
            'qa_status'   => TicketQaStatusEnum::class,
            'qa_requested_at' => 'datetime',
            'qa_checked_at' => 'datetime',
            'rated_at'    => 'datetime',
            'assigned_at' => 'datetime',
            'started_at'  => 'datetime',
            'waiting_at'  => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at'   => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Ticket $ticket) {
            if ($ticket->wasRecentlyCreated || $ticket->wasChanged(['reference', 'subject', 'description', 'tags', 'reporter_id', 'assignee_id', 'customer_id'])) {
                self::refreshSearchVectors($ticket->id);
            }
        });
    }

    /**
     * Weighted Postgres full text index, rebuilt at the point of change for one ticket or for all of them:
     * A reference and subject, B description and tags, C comments, D the people on the ticket.
     * Internal comments live in their own vector so only lead engineers search them.
     */
    public static function refreshSearchVectors(?int $ticketId = null): void
    {
        DB::update(
            "UPDATE tickets t SET
                search_vector = setweight(to_tsvector('english', concat_ws(' ', t.reference, replace(t.reference, '-', ' '), t.subject)), 'A')
                    || setweight(to_tsvector('english', concat_ws(' ', t.description, (SELECT string_agg(tag, ' ') FROM jsonb_array_elements_text(t.tags) tag))), 'B')
                    || setweight(to_tsvector('english', coalesce((SELECT string_agg(c.body, ' ') FROM ticket_comments c WHERE c.ticket_id = t.id AND NOT c.is_internal), '')), 'C')
                    || setweight(to_tsvector('simple', concat_ws(' ', ru.username, ru.contact_name, au.username, au.contact_name, cu.name, cu.contact_name)), 'D'),
                internal_search_vector = setweight(to_tsvector('english', coalesce((SELECT string_agg(c.body, ' ') FROM ticket_comments c WHERE c.ticket_id = t.id AND c.is_internal), '')), 'C')
            FROM tickets s
                LEFT JOIN users ru ON s.reporter_type = 'User' AND ru.id = s.reporter_id
                LEFT JOIN users au ON au.id = s.assignee_id
                LEFT JOIN customers cu ON cu.id = s.customer_id
            WHERE s.id = t.id".($ticketId ? ' AND t.id = ?' : ''),
            $ticketId ? [$ticketId] : []
        );
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

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_collaborators')->withPivot('added_by_id')->withTimestamps();
    }

    public function qaUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_user_id');
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

    public static function canCheckQa(?User $user): bool
    {
        return $user !== null && ($user->authTo('help-desk.qa') || $user->authTo('help-desk.assign'));
    }

    public static function canBeRaisedBy(?User $user): bool
    {
        return $user !== null;
    }

    public static function canBeAssignedBy(?User $user): bool
    {
        return $user !== null && $user->authTo('help-desk.assign');
    }

    public function canChangeKindAndModuleBy(?User $user): bool
    {
        return $user !== null && (self::canBeAssignedBy($user) || $this->assignee_id === $user->id);
    }

    public function isAssignedTo(?User $user): bool
    {
        return $user !== null && $this->assignee_id === $user->id;
    }

    public function hasCollaborator(?User $user): bool
    {
        return $user !== null && $this->collaborators()->whereKey($user->id)->exists();
    }

    public function canBeUpdatedBy(?User $user): bool
    {
        return self::canBeAssignedBy($user) || (self::canBeManagedBy($user) && $this->isAssignedTo($user));
    }

    public function canContributeBy(?User $user): bool
    {
        return $this->canBeUpdatedBy($user) || $this->hasCollaborator($user);
    }

    public function canManageCollaboratorsBy(?User $user): bool
    {
        return $this->canBeUpdatedBy($user);
    }

    public function canPreviewAttachmentsBy(?User $user): bool
    {
        return $user !== null && (self::canBeManagedBy($user) || self::canCheckQa($user) || $this->isReportedBy($user));
    }

    public function canSeeInternalNotesBy(?User $user): bool
    {
        return $user !== null && (self::canBeAssignedBy($user) || self::canBeManagedBy($user) || $this->canContributeBy($user));
    }

    public static function canUseAssistant(?User $user): bool
    {
        return self::canBeManagedBy($user) || self::canCheckQa($user);
    }

    public function commentsVisibleTo(mixed $viewer): HasMany
    {
        $isLead           = $viewer instanceof User && self::canBeAssignedBy($viewer);
        $seesInternalNote = $viewer instanceof User && $this->canSeeInternalNotesBy($viewer);

        return $this->comments()
            ->when(!$isLead, fn ($query) => $query->where('is_lead_only', false))
            ->when(!$seesInternalNote, fn ($query) => $query->where('is_internal', false));
    }

    public function defaultWaitingHours(): int
    {
        return $this->reporter_type === 'User' && !in_array($this->kind?->value, TicketKindEnum::internalValues(), true) ? 72 : 14 * 24;
    }

    public function isReportedBy(?User $user): bool
    {
        return $user !== null && $this->reporter_type === 'User' && $this->reporter_id === $user->id;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (self::canBeAssignedBy($user)) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->where('tickets.is_confidential', false)
            ->orWhere('tickets.assignee_id', $user->id)
            ->orWhereExists(fn ($collaborators) => $collaborators->selectRaw('1')->from('ticket_collaborators')->whereColumn('ticket_collaborators.ticket_id', 'tickets.id')->where('ticket_collaborators.user_id', $user->id))
            ->orWhere(fn (Builder $query) => $query->where('tickets.reporter_type', 'User')->where('tickets.reporter_id', $user->id)));
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->whereKey($this->id)->visibleTo($user)->exists();
    }

    /**
     * @return array<int, array{name: string, url: string, mime: string|null, size: int, created_at: mixed, thumbnail: array<string, string>|null}>
     */
    public function attachmentGalleryFor(User|WebUser $viewer, string $routeName = 'grp.tickets.attachments.show'): array
    {
        $visibleCommentIds = $this->commentsVisibleTo($viewer)->pluck('id');

        return Media::query()
            ->whereIn('collection_name', ['ticket_images', 'ticket_attachments'])
            ->where(fn ($query) => $query
                ->where(fn ($ticketMedia) => $ticketMedia->where('model_type', $this->getMorphClass())->where('model_id', $this->id))
                ->orWhere(fn ($commentMedia) => $commentMedia->where('model_type', (new TicketComment())->getMorphClass())->whereIn('model_id', $visibleCommentIds)))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Media $media) => [
                'name'       => $media->name,
                'url'        => route($routeName, ['ticket' => $this->reference, 'media' => $media->ulid]),
                'mime'       => $media->mime_type,
                'size'       => $media->size,
                'created_at' => $media->created_at,
                'thumbnail'  => $media->collection_name === 'ticket_images' ? GetPictureSources::run($media->getImage()->resize(400, 0)) : null,
            ])
            ->all();
    }

    public function hasAttachmentVisibleTo(Media $media, User|WebUser $viewer): bool
    {
        if (!in_array($media->collection_name, ['ticket_images', 'ticket_attachments'], true)) {
            return false;
        }

        if ($media->model_type === $this->getMorphClass()) {
            return (int) $media->model_id === $this->id;
        }

        return $media->model_type === (new TicketComment())->getMorphClass()
            && $this->commentsVisibleTo($viewer)->whereKey($media->model_id)->exists();
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Ticket::class, 'model_id')->where('model_type', 'Ticket');
    }
}

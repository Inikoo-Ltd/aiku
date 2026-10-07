<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Enums\Helpers\Ticket\TicketCommentTypeEnum;
use App\Enums\Helpers\Ticket\TicketQaStatusEnum;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasTicketImages;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $ticket_id
 * @property string|null $author_type
 * @property int|null $author_id
 * @property string $body
 * @property bool $is_internal
 * @property bool $is_lead_only
 * @property TicketCommentTypeEnum $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent|null $author
 * @property-read Ticket $ticket
 * @mixin \Eloquent
 */
class TicketComment extends Model implements HasMedia
{
    use InteractsWithMedia;
    use HasTicketImages;

    protected $guarded = [];

    protected $attributes = [
        'type' => 'comment',
    ];

    protected static function booted(): void
    {
        $refresh = function (TicketComment $comment) {
            Ticket::refreshSearchVectors($comment->ticket_id);
            $comment->ticket?->broadcastUpdated();
        };
        static::saved($refresh);
        static::deleted($refresh);
    }

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'is_lead_only' => 'boolean',
            'type'         => TicketCommentTypeEnum::class,
            'has_qa_verdict' => TicketQaStatusEnum::class,
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function isAuthoredBy(?Model $user): bool
    {
        return $user !== null && $this->author_type === class_basename($user) && (int) $this->author_id === (int) $user->id;
    }

    /**
     * Its author, or a lead engineer tidying the thread (a comment posted under the wrong name, or by mistake).
     */
    public function canBeDeletedBy(?Model $user): bool
    {
        return $this->isAuthoredBy($user) || ($user instanceof User && Ticket::canBeAssignedBy($user));
    }
}

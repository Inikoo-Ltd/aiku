<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Enums\Helpers\Ticket\TicketProjectHealthEnum;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_project_id
 * @property int|null $author_id
 * @property TicketProjectHealthEnum|null $health
 * @property string $body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $author
 * @property-read TicketProject $project
 * @mixin \Eloquent
 */
class TicketProjectUpdate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'health' => TicketProjectHealthEnum::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TicketProject::class, 'ticket_project_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isAuthoredBy(?User $user): bool
    {
        return $user !== null && $this->author_id !== null && (int) $this->author_id === (int) $user->id;
    }

    public function canBeEditedBy(?User $user): bool
    {
        return $this->isAuthoredBy($user) && $user->group_id === $this->project->group_id;
    }

    public function canBeDeletedBy(?User $user): bool
    {
        if ($user === null || $user->group_id !== $this->project->group_id) {
            return false;
        }

        return $this->isAuthoredBy($user) || Ticket::canBeAssignedBy($user) || (int) $this->project->owner_id === (int) $user->id;
    }
}

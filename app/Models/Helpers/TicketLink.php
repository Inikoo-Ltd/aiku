<?php

/*
 * Author Louis Perez
 * Created on 07-10-2026-13h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Helpers;

use App\Enums\Helpers\Ticket\TicketLinkTypeEnum;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Jira style link between two tickets, read outward from ticket_id and inward from linked_ticket_id.
 *
 * @property int $id
 * @property int $group_id
 * @property int $ticket_id
 * @property int $linked_ticket_id
 * @property TicketLinkTypeEnum $type
 * @property int|null $created_by_id
 * @property-read Ticket $ticket
 * @property-read Ticket $linkedTicket
 */
class TicketLink extends Model
{
    protected $guarded = [];

    protected $casts = [
        'type' => TicketLinkTypeEnum::class,
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function linkedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'linked_ticket_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}

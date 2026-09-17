<?php

/*
 * Author Louis Perez
 * Created on 17-09-2026-13h-23m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Recommendations;

use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;

interface RelatedTicketFinder
{
    /**
     * @return Collection<int, Ticket>
     */
    public function find(Ticket $ticket, User $viewer, int $limit = 5): Collection;
}

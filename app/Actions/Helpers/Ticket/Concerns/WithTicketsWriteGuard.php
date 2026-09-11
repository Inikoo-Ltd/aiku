<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\Concerns;

use App\Enums\Helpers\Ticket\TicketTypeEnum;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait WithTicketsWriteGuard
{
    public static function ticketsAreReadOnly(TicketTypeEnum|string $type = TicketTypeEnum::HELP): bool
    {
        $type = $type instanceof TicketTypeEnum ? $type->value : $type;

        return in_array($type, config('tickets.read_only_types', []), true);
    }

    public static function readOnlyMessage(): string
    {
        return __('Tickets are read-only while we mirror Jira. Please raise or update the ticket in Jira until the cut-over.');
    }

    protected function guardTicketsWritable(TicketTypeEnum|string $type): void
    {
        if (self::ticketsAreReadOnly($type)) {
            throw new HttpException(423, self::readOnlyMessage());
        }
    }
}

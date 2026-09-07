<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\Concerns;

use Symfony\Component\HttpKernel\Exception\HttpException;

trait WithTicketsWriteGuard
{
    public static function ticketsAreReadOnly(): bool
    {
        return (bool) config('tickets.read_only');
    }

    public static function readOnlyMessage(): string
    {
        return __('Tickets are read-only while we mirror Jira. Please raise or update the ticket in Jira until the cut-over.');
    }

    protected function guardTicketsWritable(): void
    {
        if (self::ticketsAreReadOnly()) {
            throw new HttpException(423, self::readOnlyMessage());
        }
    }
}

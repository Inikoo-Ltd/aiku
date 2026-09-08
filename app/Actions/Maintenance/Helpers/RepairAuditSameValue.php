<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Jan 2025 14:56:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Helpers;

use App\Models\Helpers\Audit;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairAuditSameValue
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $audits = Audit::whereColumn('old_values', 'new_values')
            ->where('old_values', '!=', '[]');

        $audits->delete();
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:audit_same_values';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        try {
            $this->handle();
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

}

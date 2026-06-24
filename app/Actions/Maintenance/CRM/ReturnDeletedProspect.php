<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 22 May 2026 15:40:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Maintenance\CRM;

use App\Models\CRM\Prospect;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ReturnDeletedProspect
{
    use AsAction;

    public function handle(?string $deletedAt = null): void
    {
        Prospect::whereNotNull('deleted_at')->whereDate('deleted_at', $deletedAt)->restore();
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:return_deleted_prospects {deleted_at}';
    }

    public function asCommand(Command $command): int
    {
        $deletedAt = $command->argument('deleted_at');

        $command->info('Starting return deleted prospects at specified date.');


        try {
            $this->handle($deletedAt);
            $command->info("Deleted prospects returned successfully on " . $deletedAt);
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}

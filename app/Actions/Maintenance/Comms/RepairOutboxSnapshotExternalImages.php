<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Oct 2026 15:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Maintenance\Comms;

use App\Models\Comms\Outbox;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOutboxSnapshotExternalImages
{
    use AsAction;
    use WithRepairExternalImages;

    /**
     * @return array{msg: string, replaced: int, failed: array<int, string>}
     */
    public function handle(Outbox $outbox): array
    {
        $email = $outbox->emailOngoingRun?->email;
        if (!$email) {
            return ['msg' => 'outbox has no email', 'replaced' => 0, 'failed' => []];
        }

        return $this->repairEmailSnapshots($email, $outbox);
    }

    public string $commandSignature = 'repair:outbox-snapshot-external-images {outboxes?* : outbox slugs, re-runs even if already repaired} {--shop= : only outboxes of this shop slug}';

    public function asCommand(Command $command): int
    {
        return $this->repairExternalImagesFromCommand(
            $command,
            Outbox::whereHas('emailOngoingRun.email'),
            $command->argument('outboxes'),
            $command->option('shop')
        );
    }
}

<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Oct 2026 14:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Maintenance\Comms;

use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMailshotSnapshotExternalImages
{
    use AsAction;
    use WithRepairExternalImages;

    /**
     * @return array{msg: string, replaced: int, failed: array<int, string>}
     */
    public function handle(Mailshot $mailshot): array
    {
        if (!$mailshot->email) {
            return ['msg' => 'mailshot has no email', 'replaced' => 0, 'failed' => []];
        }

        return $this->repairEmailSnapshots($mailshot->email, $mailshot);
    }

    public string $commandSignature = 'repair:mailshot-snapshot-external-images {mailshots?* : mailshot slugs, re-runs even if already repaired} {--shop= : only mailshots of this shop slug} {--all-item : every sent mailshot, not only those of the last '.self::RECENT_WEEKS.' weeks}';

    private const int RECENT_WEEKS = 2;

    public function asCommand(Command $command): int
    {
        $query = Mailshot::whereIn('state', [MailshotStateEnum::SENT, MailshotStateEnum::SENDING]);

        if (!$command->option('all-item') && !$command->argument('mailshots')) {
            $query->where('date', '>=', now()->subWeeks(self::RECENT_WEEKS));
        }

        return $this->repairExternalImagesFromCommand(
            $command,
            $query,
            $command->argument('mailshots'),
            $command->option('shop')
        );
    }
}

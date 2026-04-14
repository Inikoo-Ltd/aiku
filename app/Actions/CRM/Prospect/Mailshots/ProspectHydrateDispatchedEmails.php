<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 9 Apr 2026 11:06:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Models\CRM\Prospect;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ProspectHydrateDispatchedEmails implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(int $prospectId): string
    {
        return $prospectId;
    }

    public function handle(int $prospectId): void
    {
        $prospect = Prospect::find($prospectId);

        if (!$prospect) {
            return;
        }

        $numberEmailsSent = DB::table('prospect_has_dispatched_emails')
            ->where('prospect_has_dispatched_emails.prospect_id', $prospectId)
            ->count();

        $prospect->update([
            'number_dispatched_emails' => $numberEmailsSent
        ]);
    }

    public string $commandSignature = 'prospect:hydrate-emails-sent {prospectId}';

    public function asCommand(Command $command): int
    {
        $prospectId = $command->argument('prospectId');
        $this->handle($prospectId);

        return 0;
    }
}

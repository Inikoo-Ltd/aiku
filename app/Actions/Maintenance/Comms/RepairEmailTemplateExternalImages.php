<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Oct 2026 15:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Maintenance\Comms;

use App\Models\Comms\EmailTemplate;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEmailTemplateExternalImages
{
    use AsAction;
    use WithRepairExternalImages;

    /**
     * @return array{msg: string, replaced: int, failed: array<int, string>}
     */
    public function handle(EmailTemplate $emailTemplate): array
    {
        $this->aikuUrls = [];

        [$layout, $compiledLayout] = $this->repairLayout(
            $emailTemplate->shop ?? $emailTemplate->group,
            $emailTemplate->layout ?? [],
            $emailTemplate->compiled_layout
        );

        $emailTemplate->updateQuietly([
            'layout'          => $layout,
            'compiled_layout' => $compiledLayout,
        ]);

        return $this->recordExternalImagesRepair($emailTemplate);
    }

    public string $commandSignature = 'repair:email-template-external-images {templates?* : email template slugs, re-runs even if already repaired} {--shop= : only templates of this shop slug}';

    public function asCommand(Command $command): int
    {
        return $this->repairExternalImagesFromCommand(
            $command,
            EmailTemplate::query(),
            $command->argument('templates'),
            $command->option('shop')
        );
    }
}

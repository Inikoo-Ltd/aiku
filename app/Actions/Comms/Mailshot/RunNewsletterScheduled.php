<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 7 Jan 2026 11:21:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Services\QueryBuilder;
use App\Models\Comms\Mailshot;

class RunNewsletterScheduled
{
    use AsAction;
    public string $jobQueue = 'default-long';
    public string $commandSignature = 'run-newsletter-scheduled';
    public string $commandDescription = 'Run newsletter scheduled';

    public function handle(): void
    {
        // TODO: Implement newsletter scheduled run logic
        // use mailshot as main table

        $newsletterQuery = QueryBuilder::for(Mailshot::class);
        $newsletterQuery->where('type', MailshotTypeEnum::NEWSLETTER);
        $newsletterQuery->where('state', MailshotStateEnum::SCHEDULED);
        $newsletterQuery->whereNull('sent_at');
        $newsletterQuery->whereNull('source_id'); // to avoid resending newsletter that imported from Aurora
        $newsletterQuery->whereNull('source_alt_id'); // to avoid resending newsletter that imported from Aurora
        $newsletterQuery->whereNull('source_alt2_id'); // to avoid resending newsletter that imported from Aurora
        $newsletterQuery->where('scheduled_at', '<=', now()); // Note: update this condition

        foreach ($newsletterQuery->cursor() as $newsletter) {
            ProcessSendNewsletter::dispatch($newsletter);
        }

    }

    public function asCommand(): void
    {
        $this->run();
    }

}

<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Wed, 11 Nov 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Web\Webpage;

use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Publishes the webpages whose scheduled publish time has come, on behalf of who booked it.
 * A page that can no longer be published by that person (closed, a mailshot blog, or locked to
 * them since) has its booking dropped instead, and so does one whose publish fails, so a broken
 * page is not retried every minute.
 */
class RunScheduledWebpagePublishes
{
    use AsAction;

    public string $commandSignature = 'webpages:publish-scheduled';

    public function handle(): void
    {
        $dueWebpages = Webpage::query()
            ->whereNotNull('publish_scheduled_at')
            ->where('publish_scheduled_at', '<=', now())
            ->with('publishScheduledBy')
            ->get();

        foreach ($dueWebpages as $webpage) {
            $this->publishScheduledWebpage($webpage);
        }
    }

    protected function publishScheduledWebpage(Webpage $webpage): void
    {
        $scheduledBy = $webpage->publishScheduledBy;

        if (!$webpage->canBeScheduledForPublishing() || !$webpage->canBeEditedBy($scheduledBy)) {
            CancelWebpagePublishSchedule::make()->action($webpage);

            return;
        }

        $publisher = $scheduledBy ? ['publisher_type' => 'User', 'publisher_id' => $scheduledBy->id] : [];

        try {
            PublishWebpage::make()->action($webpage, $publisher, strict: false);
        } catch (Throwable $exception) {
            report($exception);
            CancelWebpagePublishSchedule::make()->action($webpage);
        }
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}

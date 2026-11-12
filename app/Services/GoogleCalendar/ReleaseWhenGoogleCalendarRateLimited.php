<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Services\GoogleCalendar;

use App\Services\Gmail\GmailClient;
use Illuminate\Http\Client\RequestException;

/**
 * Job middleware: a calendar write Google refuses for going too fast (it answers the same way as
 * Gmail) puts the job back on the queue for a minute or two instead of failing it. Any other
 * failure is thrown as before.
 */
class ReleaseWhenGoogleCalendarRateLimited
{
    public function handle(object $job, callable $next): void
    {
        try {
            $next($job);
        } catch (RequestException $exception) {
            if (! GmailClient::isRateLimited($exception->response)) {
                throw $exception;
            }

            $job->release(random_int(60, 120));
        }
    }
}
